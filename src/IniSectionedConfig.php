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
use SimpleComplex\Cache\CheckEmptyCacheInterface;
use SimpleComplex\Config\Exception\InvalidArgumentException;
use SimpleComplex\Config\Exception\ConfigurationException;
use SimpleComplex\Config\Exception\RuntimeException;

/**
 * Sectioned configuration using .ini files as source,
 * and PSR-16 cache as store.
 *
 * Usable as single instance global configuration store.
 *
 * This implementation internally arranges sections as multi-dimensional arrays,
 * but - as recommended - only exposes section children; not the section
 * as a whole.
 *
 * @package SimpleComplex\Config
 */
class IniSectionedConfig extends AbstractIniConfig implements SectionedConfigInterface
{
    /**
     * Reference to first object instantiated via the getInstance() method,
     * no matter which parent/child class the method was/is called on.
     *
     * @var IniSectionedConfig
     */
    protected static $instance;

    /**
     * First object instantiated via this method, disregarding class called on.
     *
     * @param mixed ...$constructorParams
     *
     * @return IniSectionedConfig
     *      static, really, but IDE might not resolve that.
     */
    public static function getInstance(...$constructorParams)
    {
        if (!static::$instance) {
            static::$instance = new static(...$constructorParams);
        }
        return static::$instance;
    }


    // SectionedConfigInterface.------------------------------------------------

    /**
     * Fetches a configuration variable from cache.
     *
     * Arg section only gets validated if retrieving from cache (not memory);
     * and then solely by the underlying cache store's validation.
     * Arg key doesn't get validated ever, because not strictly necessary;
     * the set() method _does_ validate key.
     *
     * @param string $section
     * @param string $key
     *      Wildcard *: (arr) the whole section or empty; ignores arg default.
     * @param mixed $default
     *
     * @return mixed|null
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *      Propagated.
     */
    public function get(string $section, string $key, $default = null)
    {
        if ($key == '*') {
            return $this->memory[$section] ?? $this->cacheStore->get($section, []);
        }
        return $this->memory[$section][$key] ?? (
                $this->cacheStore->get($section, [])[$key] ?? $default
            );
        /*
        if (isset($this->memory[$section][$key])) {
            return $this->memory[$section][$key];
        }
        $arr = $this->cacheStore->get($section);
        // ~ If array; checking for array type should nornally be redundant.
        return $arr !== null && isset($arr[$key]) ? $arr[$key] :
            $default;
        */
    }

    /**
     * Sets a configuration variable; in cache, not .ini file.
     *
     * Section and key get validated by this class prior to the underlying
     * cache store's validation, because the the cache store's validation may
     * be more forgiving than this class' ditto.
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
     * @throws RuntimeException
     *      Cache store failed silently.
     */
    public function set(string $section, string $key, $value) : bool
    {
        if (!$this->keyValidate($section)) {
            throw new InvalidArgumentException(
                'Arg section does not conform with .ini file and/or cache key requirements, section[' . $section . '].'
            );
        }
        if (!$this->keyValidate($key)) {
            throw new InvalidArgumentException(
                'Arg key does not conform with .ini file and/or cache key requirements, key[' . $key . '].'
            );
        }
        /**
         * @see SectionedConfigInterface::remember()
         */
        unset($this->memory[$section]);

        $arr = $this->cacheStore->get($section, []);
        $arr[$key] = $value;
        // Save to cache.
        if (!$this->cacheStore->set($section, $arr)) {
            throw new RuntimeException(
                'Underlying cache store type[' . get_class($this->cacheStore)
                . '] failed to set (save) section[' . $section . '].'
            );
        }
        return true;
    }

    /**
     * Deletes a configuration variable; from cache, not .ini file.
     *
     * Also deletes the section, if arg key is the last remaining variable
     * in the section.
     *
     * @param string $section
     * @param string $key
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     *      Bad key.
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *      Propagated.
     * @throws RuntimeException
     *      Cache store failed silently.
     */
    public function delete(string $section, string $key) : bool
    {
        if (!$this->keyValidate($section)) {
            throw new InvalidArgumentException(
                'Arg section does not conform with .ini file and/or cache key requirements, section[' . $section . '].'
            );
        }
        if (!$this->keyValidate($key)) {
            throw new InvalidArgumentException(
                'Arg key does not conform with .ini file and/or cache key requirements, key[' . $key . '].'
            );
        }
        /**
         * @see SectionedConfigInterface::remember()
         */
        unset($this->memory[$section]);

        $arr = $this->cacheStore->get($section);
        // ~ If array; checking for array type should nornally be redundant.
        if ($arr !== null) {
            unset($arr[$key]);
            if (!$arr) {
                if (!$this->cacheStore->delete($section)) {
                    throw new RuntimeException(
                        'Underlying cache store type[' . get_class($this->cacheStore)
                        . '] failed to delete section[' . $section . '].'
                    );
                }
            } elseif (!$this->cacheStore->set($section, $arr)) {
                throw new RuntimeException(
                    'Underlying cache store type[' . get_class($this->cacheStore)
                    . '] failed to set (save) section[' . $section . '].'
                );
            }
        }
        return true;
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

        $arr = $this->memory[$section] ?? $this->cacheStore->get($section);
        // ~ If array; checking for array type should nornally be redundant.
        if ($arr !== null) {
            $values = [];
            foreach ($keys as $key) {
                $values[$key] = $arr[$key] ?? $default;
            }
            return $values;
        }
        return [];
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
     * @throws RuntimeException
     *      Cache store failed silently.
     */
    public function setMultiple(string $section, /*iterable*/ $values) : bool
    {
        if (!$this->keyValidate($section)) {
            throw new InvalidArgumentException(
                'Arg section does not conform with .ini file and/or cache key requirements, section[' . $section . '].'
            );
        }
        if (!is_array($values) && !is_a($values, \Traversable::class)) {
            throw new \TypeError(
                'Arg values type[' . (!is_object($values) ? gettype($values) : get_class($values))
                . '] is not iterable.'
            );
        }

        /**
         * @see SectionedConfigInterface::remember()
         */
        unset($this->memory[$section]);

        $arr = $this->cacheStore->get($section, []);
        foreach ($values as $key => $value) {
            if (!$this->keyValidate($key)) {
                throw new InvalidArgumentException(
                    'An arg values key does not conform with .ini file and/or cache key requirements, key['
                    . $key . '].'
                );
            }
            $arr[$key] = $value;
        }
        if (!$this->cacheStore->set($section, $arr)) {
            // Unlikely, but safer.
            throw new RuntimeException(
                'Underlying cache store type[' . get_class($this->cacheStore)
                . '] failed to set section[' . $section . '].'
            );
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
        return isset($this->memory[$section]) ? isset($this->memory[$section][$key]) :
            isset($this->cacheStore->get($section, [])[$key]);
    }

    /**
     * Load section into memory, to make subsequent getter calls read
     * from memory instead of physical store.
     *
     * A subsequent call to a setting or deleting method using arg section
     * will (for integrity reasons) immediately clear the section from memory.
     *
     * @param string $section
     *
     * @return bool
     *      False: section doesn't exist.
     */
    public function remember(string $section) : bool
    {
        $arr = $this->cacheStore->get($section);
        // ~ If array; checking for array type should nornally be redundant.
        if ($arr !== null) {
            $this->memory[$section] =& $arr;
            return true;
        }
        return false;
    }

    /**
     * Flush section from memory, to relieve memory usage; and make subsequent
     * getter calls read from physical store.
     *
     * @param string $section
     *
     * @return void
     */
    public function forget(string $section) /*: void*/
    {
        unset($this->memory[$section]);
    }

    // Custom/business.--------------------------------------------------------

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
     * Sectioned configuration always uses [section]s of .ini file.
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
     * @var array
     */
    protected $memory = [];

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

        // Don't import from .ini-files if our cache store has items.
        if (!$this->cacheStore->empty()) {
            return;
        }

        // Resolve 'base' and 'override' .ini-file dirs, and parse their files.
        $collection = array_replace_recursive(
            $this->findNParseIniFiles('pathBase', $options, static::PATH_BASE_DEFAULT),
            $this->findNParseIniFiles('pathOverride', $options, static::PATH_OVERRIDE_DEFAULT)
        );

        // Cache.
        if (!$this->cacheStore->setMultiple($collection)) {
            // Unlikely, but safer.
            throw new RuntimeException(
                'Underlying cache store type[' . get_class($this->cacheStore)
                . '] failed to set cache items loaded from .ini file(s).'
            );
        }
    }
}
