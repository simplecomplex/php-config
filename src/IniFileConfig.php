<?php
/**
 * SimpleComplex PHP Config
 * @link      https://github.com/simplecomplex/php-config
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Config;


use SimpleComplex\Cache\CacheBroker;
use SimpleComplex\Config\Exception\InvalidArgumentException;

/**
 * Configuration using environment variables as source, and no caching.
 *
 * @package SimpleComplex\Config
 */
class IniFileConfig implements ConfigInterface
{
    /**
     * Reference to first object instantiated via the getInstance() method,
     * no matter which parent/child class the method was/is called on.
     *
     * @var EnvVarConfig
     */
    protected static $instance;

    /**
     * First object instantiated via this method, disregarding class called on.
     *
     * @param mixed ...$constructorParams
     *
     * @return EnvVarConfig
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
     * Fetches an environment variable.
     *
     * @throws InvalidArgumentException
     *      Propagated. Implements \Psr\SimpleCache\InvalidArgumentException.
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed|null
     *      Environment vars are always string.
     *      The default may be of any type.
     */
    public function get(string $key, $default = null)
    {
        $k = $this->keyConvert($key);
        $v = getenv($k);
        return $v !== false ? $v : $default;
    }

    /**
     * Does nothing at all; setting/overwriting an environment var could have
     * security implications and/or result in peculiar errors.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return bool
     *      Always true.
     */
    public function set(string $key, $value) : bool
    {
        return true;
    }

    /**
     * Does nothing at all; setting/overwriting an environment var could have
     * security implications and/or result in peculiar errors.
     *
     * @param mixed $key
     *
     * @return bool
     *      Always true.
     */
    public function delete(string $key) : bool
    {
        return true;
    }

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable $keys
     * @param mixed $default
     *
     * @return array
     *
     * @throws \TypeError
     */
    public function getMultiple(/*iterable*/ $keys, $default = null) : array
    {
        if (!is_array($keys) && !is_a($keys, \Traversable::class)) {
            throw new \TypeError(
                'Arg keys type[' . (!is_object($keys) ? gettype($keys) : get_class($keys)) . '] is not iterable.'
            );
        }
        $values = [];
        foreach ($keys as $k) {
            $values[$k] = $this->get($k, $default);
        }
        return $values;
    }

    /**
     * Does nothing at all.
     *
     * @param iterable $values
     *
     * @return bool
     *      Always true.
     */
    public function setMultiple(/*iterable*/ $values) : bool
    {
        return true;
    }

    /**
     * Check if an environment var is set.
     *
     * @param string $key
     *
     * @return bool
     */
    public function has(string $key) : bool
    {
        $k = $this->keyConvert($key);
        return getenv($k) !== false;
    }

    /**
     * For domain:key namespaced use. Delimiter between domain and key.
     */
    const KEY_DOMAIN_DELIMITER = '[:]';

    /**
     * @return string
     */
    public function keyDomainDelimiter() : string {
        return static::KEY_DOMAIN_DELIMITER;
    }


    // Custom/business.---------------------------------------------------------

    /**
     * Path given by argument or class default; absolute or relative.
     *
     * @var string
     */
    protected $pathBase = '';

    const PATH_BASE_DEFAULT = '../conf/ini/base';

    const PATH_OVERRIDE_DEFAULT = '../conf/ini/operations';

    /**
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
     *      @var string $pathBase = ''
     *          Empty: class default (PATH_BASE_DEFAULT) rules.
     *      @var string $pathOverride = ''
     *          Empty: class default (PATH_OVERRIDE_DEFAULT) rules.
     * }
     * @throws \InvalidArgumentException
     *      Propagated. If arg name conflicts with naming rules of the cache store.
     * @throws \TypeError
     *      Wrong type of arg options bucket.
     * @throws \Throwable
     *      Propagated.
     */
    public function __construct(string $name, array $options = [])
    {
        // We need a cache store, no matter what.
        $this->cacheStore = CacheBroker::getInstance()->getStore($name);
        if (
            !is_a($this->cacheStore, \SimpleComplex\Cache\CacheInterface::class)
            && !method_exists($this->cacheStore, 'size')
        ) {
            throw new \LogicException(
                'Cache store must have a size() method, saw type['
                . (!is_object($this->cacheStore) ? gettype($this->cacheStore) : get_class($this->cacheStore)) . '].'
            );
        }
        // Don't import from .ini-files if our cache store has items.
        if ($this->cacheStore->size()) {
            return;
        }

        // Resolve 'base' and 'override' .ini-file dirs.
        if (!empty($options['path'])) {
            if (!is_string($options['path'])) {
                throw new \TypeError('Arg options[path] type['
                    . (!is_object($options['path']) ? gettype($options['path']) :
                        get_class($options['path'])) . '] is not string.');
            }
            $this->path = $options['path'];
        } else {
            $this->path = static::PATH_DEFAULT;
        }




        if (!$this->nameValidate($name)) {
            throw new InvalidArgumentException('Arg name is empty or contains illegal char(s), name['
                . $name . '].');
        }
        $this->name = $name;

        if (!empty($options['path'])) {
            if (!is_string($options['path'])) {
                throw new \TypeError('Arg options[path] type['
                    . (!is_object($options['path']) ? gettype($options['path']) :
                        get_class($options['path'])) . '] is not string.');
            }
            $this->path = $options['path'];
        } else {
            $this->path = static::PATH_DEFAULT;
        }

        // Resolve path, and load preexisting settings if they exist.
        $settings = $this->resolvePath() ? $this->loadSettings() : [];
        // Resolve options and final instance var values, and figure out if we
        // need to update filed settings.
        $save_settings = $this->resolveSettings($settings, $options);
        // Create path, cache dir and tmp dir, if they don't exist.
        $this->ensureDirectories();
        // Save/update settings.
        if ($save_settings) {
            $this->saveSettings($settings);
        }
    }

    /**
     * Legal non-alphanumeric characters of a instance name.
     *
     * Requirements:
     * - a-zA-Z\d_.\-
     * - length: >=2 <=64
     */
    const NAME_VALID_NON_ALPHANUM = [
        '-',
        '.',
        '_'
    ];

    /**
     * Checks that name length and content is legal.
     *
     * @param string $name
     *
     * @return bool
     */
    public function nameValidate(string $name) : bool
    {
        $le = strlen($name);
        if ($le < 2 || $le > 64) {
            return false;
        }
        // Faster than a regular expression.
        return !!ctype_alnum('A' . str_replace(static::NAME_VALID_NON_ALPHANUM, '', $name));
    }


    // Rubbish.

    /**
     * Legal non-alphanumeric characters of a key.
     *
     * These keys are selected because they would work in the most basic cache
     * implementation; that is: file (dir names and filenames).
     */
    const KEY_VALID_NON_ALPHANUM = [
        '(',
        ')',
        '-',
        '.',
        ':',
        '[',
        ']',
        '_'
    ];

    /**
     * Checks that key is string, and that length and content is legal.
     *
     * @param string $key
     *
     * @return bool
     */
    public function keyValidate(string $key) : bool
    {
        $le = strlen($key);
        if ($le < 2 || $le > 64) {
            return false;
        }
        // Faster than a regular expression.
        return !!ctype_alnum('A' . str_replace(static::KEY_VALID_NON_ALPHANUM, '', $key));
    }

    /**
     * Replaces all legal non-alphanumeric chars with underscore.
     *
     * @param string $key
     *
     * @return string
     *
     * @throws InvalidArgumentException
     */
    public function keyConvert(string $key) : string
    {
        if (!$key && $key === '') {
            throw new InvalidArgumentException('Arg key is empty.');
        }
        $key = str_replace(static::KEY_VALID_NON_ALPHANUM, '_', $key);
        if (!ctype_alnum(str_replace('_', '', $key))) {
            throw new InvalidArgumentException('Arg key contains invalid character(s).');
        }
        return $key;
    }
}
