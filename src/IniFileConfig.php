<?php
/**
 * SimpleComplex PHP Config
 * @link      https://github.com/simplecomplex/php-config
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Config;

use SimpleComplex\Utils\Utils;
use SimpleComplex\Cache\CacheBroker;
use SimpleComplex\Cache\CheckEmptyCacheInterface;
use SimpleComplex\Cache\Exception\RuntimeException;
use SimpleComplex\Config\Exception\InvalidArgumentException;
use SimpleComplex\Config\Exception\ConfigurationException;

/**
 * Configuration using .ini files as source, and PSR-16 cache as store.
 *
 * @package SimpleComplex\Config
 */
class IniFileConfig implements ConfigInterface
{
    // @todo: rename to IniNCacheConfig CachedIniConfig IniSectionedConfig

    /**
     * Reference to first object instantiated via the getInstance() method,
     * no matter which parent/child class the method was/is called on.
     *
     * @var IniFileConfig
     */
    protected static $instance;

    /**
     * First object instantiated via this method, disregarding class called on.
     *
     * @param mixed ...$constructorParams
     *
     * @return IniFileConfig
     *      static, really, but IDE might not resolve that.
     */
    public static function getInstance(...$constructorParams)
    {
        if (!static::$instance) {
            static::$instance = new static(...$constructorParams);
        }
        return static::$instance;
    }


    // ConfigInterface.----------------------------------------------------------

    /**
     * Fetches a configuration variable from cache.
     *
     * Key validation relies solely on the underlying cache store's validation.
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed|null
     *      Environment vars are always string.
     *      The default may be of any type.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *      Propagated.
     */
    public function get(string $key, $default = null)
    {
        return $this->cacheStore->get($key, $default);
    }

    /**
     * Sets a configuration variable; in cache, not .ini file.
     *
     * Key gets validated by this class prior to the underlying cache store's
     * validation, because the the cache store's validation may be more
     * forgiving than this class' ditto.
     * For forwards compatibility key must conform with .ini file requirements.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     *      Bad key.
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *      Propagated.
     */
    public function set(string $key, $value) : bool
    {
        if (!$this->keyValidate($key)) {
            throw new InvalidArgumentException(
                'Arg key does not conform with .ini file key requirements, key[' . $key . '].'
            );
        }
        return $this->cacheStore->set($key, $value);
    }

    /**
     * Deletes a configuration variable; from cache, not .ini file.
     *
     * @param string $key
     *
     * @return bool
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *      Propagated.
     */
    public function delete(string $key) : bool
    {
        return $this->cacheStore->delete($key);
    }

    /**
     * Obtains multiple config items by their unique keys, from cache.
     *
     * @param iterable $keys
     * @param mixed $default
     *
     * @return array
     *
     * @throws \TypeError
     *      Arg keys not iterable.
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *      Propagated.
     */
    public function getMultiple(/*iterable*/ $keys, $default = null) : array
    {
        if (!is_array($keys) && !is_a($keys, \Traversable::class)) {
            throw new \TypeError(
                'Arg keys type[' . (!is_object($keys) ? gettype($keys) : get_class($keys)) . '] is not iterable.'
            );
        }
        return $this->cacheStore->getMultiple($keys, $default);
    }

    /**
     * Persists a set of key => value pairs; in the cache, not .ini file.
     *
     * @param iterable $values
     *
     * @return bool
     *      Always true.
     *
     * @throws \TypeError
     *      Arg values not iterable.
     * @throws InvalidArgumentException
     *      Bad key.
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *      Propagated.
     * @throws \SimpleComplex\Cache\Exception\RuntimeException
     *      Cache store failed silently.
     */
    public function setMultiple(/*iterable*/ $values) : bool
    {
        if (!is_array($values) && !is_a($values, \Traversable::class)) {
            throw new \TypeError(
                'Arg values type[' . (!is_object($values) ? gettype($values) : get_class($values)) . '] is not iterable.'
            );
        }
        foreach ($values as $key => $value) {
            if (!$this->keyValidate($key)) {
                throw new InvalidArgumentException(
                    'An arg values key does not conform with .ini file key requirements, key[' . $key . '].'
                );
            }
            if (!$this->cacheStore->set($key, $value)) {
                // Unlikely, but safer.
                throw new RuntimeException(
                    'Underlying cache store type[' . get_class($this->cacheStore)
                    . '] failed to set a cache item, key[' . $key . '].'
                );
            }
        }
        return true;
    }

    /**
     * Check if a configuration item is set; in cache store, not (necessarily)
     * in .ini file.
     *
     * @param string $key
     *
     * @return bool
     */
    public function has(string $key) : bool
    {
        return $this->cacheStore->has($key);
    }

    /**
     * @return string
     */
    public function keyDomainDelimiter() : string {
        return static::KEY_DOMAIN_DELIMITER;
    }


    // Custom/business.--------------------------------------------------------

    /**
     * Legal non-alphanumeric characters of a key,
     * excluding a key-domain-delimiter.
     *
     * NB: Square brackets are only allowed in a key-domain-delimiter;
     * actually a key-domain-delimiter MUST contain at least one square bracket.
     *
     * The only non-alphanumeric characters allowed in .ini file section
     * and variable names are hyphen, dot and underscore.
     * And spaces - but spaces are not allowed here because they can make a lot
     * of problems throughout a system.
     *
     * PSR-16 requirements:
     * - at least: a-zA-Z\d_.
     * - not: {}()/\@:
     * - length: >=2 <=64
     *
     * Do not override; not healthy.
     *
     * @var string[]
     */
    const KEY_VALID_NON_ALPHANUM = [
        '-',
        '.',
        '_'
    ];

    /**
     * Whether configuration is sectioned into configuration 'domains', or flat.
     *
     * Flat configuration
     * ------------------
     * All [section]s in .ini files will be ignored; all vars will be seen as
     * 'flat' unordered items.
     *
     * Domain-sectioned configuation
     * -----------------------------
     * All .ini files must use sections - contain a [section] before first var.
     * All vars will be parse into [section][key-domain-delimiter][var name]
     * keys.
     *
     * Allowed values: domainSectioned|flat.
     *
     * @see IniFileConfig::keyDomainDelimiter()
     *
     * @var string
     */
    const KEY_MODE_DEFAULT = 'domainSectioned';

    /**
     * Domain-sectioned key mode only.
     *
     * For domain:key namespaced use. Delimiter between domain and key.
     *
     * @var string
     */
    const KEY_DOMAIN_DELIMITER = '[.]';

    /**
     * Path to directory where base configuration .ini-files reside.
     *
     * Base configuration should work in dev/test environments.
     *
     * @var string
     */
    const PATH_BASE_DEFAULT = '../conf/ini/base';

    /**
     * Path to directory where overriding configuration .ini-files reside.
     *
     * Overriding configuration should consist of productions settings.
     *
     * @var string
     */
    const PATH_OVERRIDE_DEFAULT = '../conf/ini/operations';

    /**
     * Values: domainSectioned|flat.
     *
     * @var string
     */
    protected $keyMode;

    /**
     * @var int
     */
    protected $keyDomainDelimiterLength = 0;

    /**
     * Config's cache store.
     *
     * @var \Psr\SimpleCache\CacheInterface
     */
    protected $cacheStore;

    /**
     * Create or load configuration store.
     *
     * @uses CacheBroker::getStore()
     *
     * @param string $name
     * @param array $options {
     *      @var string $keyMode = ''
     *          Empty: class default (KEY_MODE_DEFAULT) rules.
     *      @var string $pathBase = ''
     *          Empty: class default (PATH_BASE_DEFAULT) rules.
     *      @var string $pathOverride = ''
     *          Empty: class default (PATH_OVERRIDE_DEFAULT) rules.
     * }
     * @throws \LogicException
     *      CacheBroker returns a cache store which has no empty() method.
     * @throws \TypeError
     *      Wrong type of an arg options bucket.
     * @throws InvalidArgumentException
     *      Bad value of an arg options bucket.
     * @throws ConfigurationException
     *      Propagated. If pathBase or pathOverride doesn't exist or isn't directory.
     * @throws \Throwable
     *      Propagated.
     */
    public function __construct(string $name, array $options = [])
    {
        // We need a cache store, no matter what.
        $this->cacheStore = CacheBroker::getInstance()->getStore($name);
        // The cache store must have an empty() method.
        if (
            !is_a($this->cacheStore, CheckEmptyCacheInterface::class)
            && !method_exists($this->cacheStore, 'empty')
        ) {
            throw new \LogicException(
                'Cache store must have an empty() method, saw type['
                . (!is_object($this->cacheStore) ? gettype($this->cacheStore) : get_class($this->cacheStore)) . '].'
            );
        }

        if (!empty($options['keyMode'])) {
            if (!is_string($options['keyMode'])) {
                throw new \TypeError('Arg options[keyMode] type['
                    . (!is_object($options['keyMode']) ? gettype($options['keyMode']) :
                        get_class($options['keyMode'])) . '] is not string.');
            }
            switch ($options['keyMode']) {
                case 'domainSectioned':
                case 'flat':
                    $this->keyMode = $options['keyMode'];
                    break;
                default:
                    throw new InvalidArgumentException(
                        'Arg options[keyMode] must be domainSectioned|flat, saw[' . $options['keyMode'] . '].'
                    );
            }
        } else {
            $this->keyMode = static::KEY_MODE_DEFAULT;
        }
        if ($this->keyMode == 'domainSectioned') {
            $this->keyDomainDelimiterLength = strlen(static::KEY_DOMAIN_DELIMITER);
        }

        // Don't import from .ini-files if our cache store has items.
        if (!$this->cacheStore->empty()) {
            return;
        }

        $this->utils = Utils::getInstance();

        // Resolve 'base' and 'override' .ini-file dirs, and parse their files.
        $collection = array_replace_recursive(
            $this->findNParseIniFiles('pathBase', $options, static::PATH_BASE_DEFAULT),
            $this->findNParseIniFiles('pathOverride', $options, static::PATH_OVERRIDE_DEFAULT)
        );

        // Flatten, using the key-domain-delimiter.
        if ($this->keyMode == 'domainSectioned') {
            $flattened = [];
            $delimiter = static::KEY_DOMAIN_DELIMITER;
            foreach ($collection as $section => $sub) {
                foreach ($sub as $key => $value) {
                    $flattened[$section . $delimiter . $key] = $value;
                }
            }
            $collection =& $flattened;
        }

        // Cache.
        $this->cacheStore->setMultiple($collection);
    }

    /**
     * Resolves base and override paths, and parses all their .ini files.
     *
     * @param string $pathName
     * @param array $options
     * @param string $pathDefault
     *
     * @return array
     *
     * @throws \TypeError
     *      Wrong type of arg options bucket.
     * @throws ConfigurationException
     *      If the path doesn't exist or isn't directory.
     *      if keyMode is domainSectioned and an .ini file doesn't declare
     *      a [section] before flat vars.
     */
    protected function findNParseIniFiles(string $pathName, array $options, string $pathDefault) : array
    {
        $utils = Utils::getInstance();
        if (!empty($options[$pathName])) {
            if (!is_string($options[$pathName])) {
                throw new \TypeError('Arg options[' . $pathName . '] type['
                    . (!is_object($options[$pathName]) ? gettype($options[$pathName]) :
                        get_class($options[$pathName])) . '] is not string.');
            }
            $path = $utils->resolvePath($options[$pathName]);
        } else {
            $path = $utils->resolvePath($pathDefault);
        }
        if (!file_exists($path) || !is_dir($path)) {
            throw new ConfigurationException(
                $pathName . ' for configuration .ini files '
                . (!file_exists($this->{$path}) ? 'does not exist' : 'is not a directory') . ', path[' . $path . '].'
            );
        }

        $collection = [];
        $dir_iterator = new \DirectoryIterator($path);
        foreach ($dir_iterator as $item) {
            if (!$item->isDot() && $item->getExtension() == 'ini') {

                // @todo: require sectioned.
                if ($this->keyMode == 'domainSectioned') {
                    // Check that the whole configuration begins with a [section].
                    $ini = file_get_contents($path . '/' . $item->getFilename());
                    // Remove comments and leading empty lines.
                    $ini = ltrim(
                        preg_replace(
                            '/\n;[^\n]+\n/m',
                            "\n",
                            "\n" . str_replace("\r", '', $ini)
                        )
                    );
                    if (!trim($ini)) {
                        continue;
                    }
                    if (!preg_match('/^\[/', $ini)) {
                        throw new ConfigurationException(
                            'In domainSectioned keyMode an .ini file must declare a [section] before flat vars, file['
                            . $path . '/' . $item->getFilename() . '].'
                        );
                    }
                    // Union; two files within same dir shouldn't declare the
                    // the same vars.
                    // But if they do, the latter will rule.
                    $collection += $utils->parseIniString($ini, true, true);
                } else {
                    $collection += $utils->parseIniFile($path . '/' . $item->getFilename(), true, true);
                }
            }
        }
        return $collection;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function keyValidate(string $key) : bool
    {
        $le = strlen($key);
        if ($le < (2 + $this->keyDomainDelimiterLength) || $le > 64) {
            return false;
        }

        if ($this->keyMode == 'domainSectioned') {
            // There can only - and must be - a single domain delimiter.
            if (substr_count($key, static::KEY_DOMAIN_DELIMITER, 1) != 1) {
                return false;
            }
            $haystack = str_replace(static::KEY_DOMAIN_DELIMITER, '', $key);
        } else {
            $haystack = $key;
        }

        // Faster than a regular expression.
        return !!ctype_alnum('A' . str_replace(static::KEY_VALID_NON_ALPHANUM, '', $haystack));
    }
}
