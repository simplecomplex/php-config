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
 * Simple configuration using .ini files as source, and PSR-16 cache as store.
 *
 * A single instance is probably only usable for limited purposes.
 * Multiple instances could be usable in a compartmented configuration strategy,
 * where each instance only handles say a module/component's dedicated
 * configuration.
 *
 * @property-read string $name
 *
 * @package SimpleComplex\Config
 */
class IniConfig extends AbstractIniConfig implements ConfigInterface
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


    // ConfigInterface.---------------------------------------------------------

    use PropertyNameTrait;

    /**
     * Fetches a configuration variable from cache.
     *
     * Key validation relies solely on the underlying cache store's validation.
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed|null
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
     * @throws RuntimeException
     *      Cache store failed silently.
     */
    public function set(string $key, $value) : bool
    {
        if (!ConfigKey::validate($key)) {
            throw new InvalidArgumentException(
                'Arg key does not conform with .ini file and/or cache key requirements, key[' . $key . '].'
            );
        }
        if (!$this->cacheStore->set($key, $value)) {
            // Unlikely, but safer.
            throw new RuntimeException(
                'Underlying cache store type[' . get_class($this->cacheStore)
                . '] failed to set cache item, key[' . $key . '].'
            );
        }
        return true;
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
     * @throws RuntimeException
     *      Cache store failed silently.
     */
    public function delete(string $key) : bool
    {
        if (!$this->cacheStore->delete($key)) {
            // Unlikely, but safer.
            throw new RuntimeException(
                'Underlying cache store type[' . get_class($this->cacheStore)
                . '] failed to delete cache item, key[' . $key . '].'
            );
        }
        return true;
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
     * @throws RuntimeException
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
            if (!ConfigKey::validate($key)) {
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
     * @param string $key
     *
     * @return bool
     */
    public function has(string $key) : bool
    {
        return $this->cacheStore->has($key);
    }


    // Custom/business.--------------------------------------------------------

    /**
     * Whether to use [section]s of .ini file, or ignore them.
     *
     * @var bool
     */
    const USE_SOURCE_SECTIONS_DEFAULT = false;

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
     * Whether to use [section]s of .ini file, or ignore them.
     *
     * @var bool
     */
    protected $useSourceSections;

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
     *      @var bool $useSourceSections
     *          Default: class default (USE_SOURCE_SECTIONS_DEFAULT) rules.
     *      @var string $pathBase
     *          Default/empty: class default (PATH_BASE_DEFAULT) rules.
     *      @var string $pathOverride
     *          Default/empty: class default (PATH_OVERRIDE_DEFAULT) rules.
     * }
     * @throws InvalidArgumentException
     *      Invalid arg name.
     *      Bad value of an arg options bucket.
     * @throws \LogicException
     *      CacheBroker returns a cache store which has no empty() method.
     * @throws \TypeError
     *      Wrong type of an arg options bucket.
     * @throws ConfigurationException
     *      Propagated. If pathBase or pathOverride doesn't exist or isn't directory.
     * @throws RuntimeException
     *      Cache store failed silently.
     * @throws \Throwable
     *      Propagated.
     */
    public function __construct(string $name, array $options = [])
    {
        if (!ConfigKey::validate($name)) {
            throw new InvalidArgumentException('Arg name is not valid, name[' . $name . '].');
        }
        $this->name = $name;

        /// We need a cache store, no matter what.
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

        $this->useSourceSections = isset($options['useSourceSections']) ? !!$options['useSourceSections'] :
            static::USE_SOURCE_SECTIONS_DEFAULT;

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
