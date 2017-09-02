<?php
/**
 * SimpleComplex PHP Config
 * @link      https://github.com/simplecomplex/php-config
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-config/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Config;

use SimpleComplex\Utils\Utils;
use SimpleComplex\Config\Interfaces\ConfigInterface;
use SimpleComplex\Config\Exception\InvalidArgumentException;
use SimpleComplex\Config\Exception\RuntimeException;

/**
 * Simple configuration using .ini files as source, and PSR-16 cache as store.
 *
 * A single instance is probably only usable for limited purposes.
 * Multiple instances could be usable in a compartmented configuration strategy,
 * where each instance only handles say a module/component's dedicated
 * configuration.
 *
 * Constructor returns effectively identical instance on second call, given
 * the same arguments; an instance is basically a wrapped cache store.
 *
 * Defaults to ignore [section]s in .ini files.
 *
 * @property-read string $name
 * @property-read bool $useSourceSections
 * @property-read bool $escapeSourceKeys
 * @property-read bool $parseTyped
 * @property-read string|null $sectionKeyDelimiter
 * @property-read array $paths
 *      Copy, to secure read-only status.
 * @property-read array $fileExtensions
 * @property-read \SimpleComplex\Cache\Interfaces\ManageableCacheInterface $cacheStore
 *
 * @package SimpleComplex\Config
 */
class IniConfig extends IniConfigBase implements ConfigInterface
{
    // ConfigInterface.---------------------------------------------------------

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
     * Retrieves multiple config items by their unique keys, from cache.
     *
     * @param array|object $keys
     * @param mixed $default
     *
     * @return array
     *
     * @throws \TypeError
     *      Arg keys not array|object.
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *      Propagated.
     */
    public function getMultiple($keys, $default = null) : array
    {
        if (!is_array($keys) && !is_object($keys)) {
            throw new \TypeError('Arg keys type[' . Utils::getType($keys) . '] is not array|object.');
        }
        return $this->cacheStore->getMultiple($keys, $default);
    }

    /**
     * Persists a set of key => value pairs; in the cache, not .ini file.
     *
     * @param array|object $values
     *
     * @return bool
     *      Always true.
     *
     * @throws \TypeError
     *      Arg values not array|object.
     * @throws InvalidArgumentException
     *      Bad key.
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *      Propagated.
     * @throws RuntimeException
     *      Cache store failed silently.
     */
    public function setMultiple($values) : bool
    {
        if (!is_array($values) && !is_object($values)) {
            throw new \TypeError('Arg values type[' . Utils::getType($values) . '] is not array|object.');
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


    // Override IniConfigBase.--------------------------------------------------

    /**
     * Paths to where configuration .ini-files reside.
     *
     * Base configuration should work in any (or no) environment.
     * Override configuration should consist of overriding/completing
     * production _or_ dev/test settings.
     *
     * Relative path is relative to document root.
     *
     * @var string[]
     */
    const PATH_DEFAULTS = [
        'base' => '../conf/ini-flat/base',
        'override' => '../conf/ini-flat/override',
    ];

    /**
     * Only these two path buckets allowed.
     *
     * If overriding class wishes to use other paths (any names and number of)
     * it should override this property.
     *
     * Relative path is relative to document root.
     *
     * @var string[]
     */
    protected $paths = [
        'base' => '',
        'override' => '',
    ];


    // Custom/business.--------------------------------------------------------

    /**
     * Create or load configuration store.
     *
     * Will return effectively identical instance on second call, when given
     * the same arguments; an instance is basically a wrapped cache store.
     *
     * Defaults to ignore [section]s in .ini files.
     *
     * @param string $name
     * @param array $paths {
     *      @var string $base
     *          Default/empty: class default (PATH_BASE_DEFAULT) rules.
     *      @var string $override
     *          Default/empty: class default (PATH_OVERRIDE_DEFAULT) rules.
     * }
     * @param array $options {
     *      @var bool $useSourceSections
     *          Default: class default (USE_SOURCE_SECTIONS_DEFAULT) rules.
     * }
     * @throws \Throwable
     *      Propagated.
     */
    public function __construct(string $name, array $paths = [], array $options = [])
    {
        $this->useSourceSections = !empty($options['useSourceSections']);

        parent::__construct($name, $paths);
    }
}
