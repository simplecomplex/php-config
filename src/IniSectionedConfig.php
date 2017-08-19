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
use SimpleComplex\Config\Exception\InvalidArgumentException;
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
 * Best for contexts where
 * -----------------------
 * one expects to access many/all keys of a section within a limited procedure;
 * allowing the use of remember() and forget().
 * Due to the internal multi-dimensional array structure; preferably
 * 'short' sections and thus few cache reads.
 *
 * Constructor returns effectively identical instance on second call, given
 * the same arguments; an instance is basically a wrapped cache store.
 *
 * Requires and uses [section]s in .ini files.
 *
 * @see Config
 *
 * @see IniConfigBase::__construct()
 *      This class doesn't specialize parent constructor,
 *      nor offers other constructor.
 *
 * @property-read string $name
 * @property-read bool $useSourceSections
 * @property-read bool $escapeSourceKeys
 * @property-read bool $parseTyped
 * @property-read string|null $sectionKeyDelimiter
 * @property-read array $paths
 *      Copy, to secure read-only status.
 * @property-read array $fileExtensions
 * @property-read \SimpleComplex\Cache\ManageableCacheInterface $cacheStore
 *
 * @package SimpleComplex\Config
 */
class IniSectionedConfig extends IniConfigBase implements SectionedConfigInterface
{
    // SectionedConfigInterface.------------------------------------------------

    /**
     * Fetches a configuration variable from cache.
     *
     * Supports the wildcard * for arg key; returning (copy) of a section.
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
        if (!ConfigKey::validate($section)) {
            throw new InvalidArgumentException(
                'Arg section does not conform with .ini file and/or cache key requirements, section[' . $section . '].'
            );
        }
        if (!ConfigKey::validate($key)) {
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
        if (!ConfigKey::validate($section)) {
            throw new InvalidArgumentException(
                'Arg section does not conform with .ini file and/or cache key requirements, section[' . $section . '].'
            );
        }
        if (!ConfigKey::validate($key)) {
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
        if ($arr !== null && array_key_exists($key, $arr)) {
            unset($arr[$key]);
            if (!$this->cacheStore->set($section, $arr)) {
                throw new RuntimeException(
                    'Underlying cache store type[' . get_class($this->cacheStore)
                    . '] failed to set (save) section[' . $section . '].'
                );
            }
        }
        return true;
    }

    /**
     * Retrieves multiple config items by their unique keys, from cache.
     *
     * @param string $section
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
    public function getMultiple(string $section, $keys, $default = null) : array
    {
        if (!is_array($keys) && !is_object($keys)) {
            throw new \TypeError('Arg keys type[' . Utils::getType($keys) . '] is not array|object.');
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
    public function setMultiple(string $section, $values) : bool
    {
        if (!ConfigKey::validate($section)) {
            throw new InvalidArgumentException(
                'Arg section does not conform with .ini file and/or cache key requirements, section[' . $section . '].'
            );
        }
        if (!is_array($values) && !is_object($values)) {
            throw new \TypeError('Arg values type[' . Utils::getType($values) . '] is not array|object.');
        }

        /**
         * @see SectionedConfigInterface::remember()
         */
        unset($this->memory[$section]);

        $arr = $this->cacheStore->get($section, []);
        foreach ($values as $key => $value) {
            if (!ConfigKey::validate($key)) {
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
     * Supports the wildcard * for arg key; checking if a section exists.
     *
     * @param string $section
     *      Wildcard *: (arr) the whole section.
     * @param string $key
     *
     * @return bool
     */
    public function has(string $section, string $key) : bool
    {
        if ($key == '*') {
            return !!($this->memory[$section] ?? $this->cacheStore->has($section));
        }
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


    // Override IniConfigBase.--------------------------------------------------

    /**
     * Requires and uses [section]s of .ini file.
     *
     * @var bool
     */
    protected $useSourceSections = true;

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
        'base' => '../conf/ini/base',
        'override' => '../conf/ini/override',
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
     * @var array
     */
    protected $memory = [];

    /**
     * Doesn't specialize parent constructor, nor offers other constructor.
     *
     * @see IniConfigBase::__construct()
     */
}
