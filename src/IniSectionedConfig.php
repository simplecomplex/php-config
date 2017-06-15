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
class IniSectionedConfig extends AbstractIniConfig implements SectionedConfigInterface
{
    /**
     * Reference to first object instantiated via the getInstance() method,
     * no matter which parent/child class the method was/is called on.
     *
     * @var IniConfig
     */
    protected static $instance;

    /**
     * First object instantiated via this method, disregarding class called on.
     *
     * @param mixed ...$constructorParams
     *
     * @return IniConfig
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
     * @param string $section
     * @param string $key
     * @param mixed $default
     *
     * @return mixed|null
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *      Propagated.
     */
    public function get(string $section, string $key, $default = null)
    {
        return $this->cacheStore->get($section . static::CACHE_KEY_SECTION_DELIMITER . $key, $default);
    }

    /**
     * Sets a configuration variable; in cache, not .ini file.
     *
     * Key gets validated by this class prior to the underlying cache store's
     * validation, because the the cache store's validation may be more
     * forgiving than this class' ditto.
     * For forwards compatibility key must conform with .ini file requirements.
     *
     * @param string $section
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
    public function set(string $section, string $key, $value) : bool
    {
        if (!$this->keyValidate($key)) {
            throw new InvalidArgumentException(
                'Arg key does not conform with .ini file and/or cache key requirements, key[' . $key . '].'
            );
        }
        return $this->cacheStore->set($key, $value);
    }

    /**
     * Deletes a configuration variable; from cache, not .ini file.
     *
     * @param string $section
     * @param string $key
     *
     * @return bool
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *      Propagated.
     */
    public function delete(string $section, string $key) : bool
    {
        return $this->cacheStore->delete($key);
    }

    /**
     * Obtains multiple config items by their unique keys, from cache.
     *
     * @param string $section
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
    public function getMultiple(string $section, /*iterable*/ $keys, $default = null) : array
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
     * @param string $section
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
    public function setMultiple(string $section, /*iterable*/ $values) : bool
    {
        if (!is_array($values) && !is_a($values, \Traversable::class)) {
            throw new \TypeError(
                'Arg values type[' . (!is_object($values) ? gettype($values) : get_class($values)) . '] is not iterable.'
            );
        }
        foreach ($values as $key => $value) {
            if (!$this->keyValidate($key)) {
                throw new InvalidArgumentException(
                    'An arg values key does not conform with .ini file and/or cache key requirements, key[' . $key . '].'
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
     * @param string $section
     * @param string $key
     *
     * @return bool
     */
    public function has(string $section, string $key) : bool
    {
        return $this->cacheStore->has($key);
    }

    /**
     * Load section into memory, to make subsequent getter calls read
     * from memory instead of physical store.
     *
     * An implementation which internally can't/won't arrange items
     * multi-dimensionally (and thus cannot load a section into memory)
     * must return null.
     *
     * @param string $section
     *
     * @return bool|null
     *      False: section doesn't exist.
     *      Null: Not applicable.
     */
    public function remember(string $section) : bool
    {
        return false;
    }

    /**
     * Flush section from memory, to relieve memory usage; and make subsequent
     * getter calls read from physical store.
     *
     * Implementations which cannot do this, must ignore call.
     *
     * @param string $section
     *
     * @return void
     */
    public function forget(string $section) /*: void*/
    {

    }

    // Custom/business.--------------------------------------------------------

    /**
     * For the composite cache key; delimiter between section and key.
     *
     * @var string
     */
    const CACHE_KEY_SECTION_DELIMITER = '[.]';

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
     * Section configuration always uses [section]s of .ini file.
     *
     * @var bool
     */
    protected $useSourceSections = true;

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
     * @param string $sectionNKey
     *
     * @return bool
     */
    public function compositeKeyValidate(string $sectionNKey) : bool
    {
        $le = strlen($key);
        if ($le < static::KEY_VALID_LENGTH['min'] || $le > static::KEY_VALID_LENGTH['max']) {
            return false;
        }
        // Faster than a regular expression.
        return !!ctype_alnum('A' . str_replace(static::KEY_VALID_NON_ALPHANUM, '', $key));
    }
}
