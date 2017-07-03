<?php
/**
 * SimpleComplex PHP Config
 * @link      https://github.com/simplecomplex/php-config
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-config/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Config;

use SimpleComplex\Config\Exception\InvalidArgumentException;
use SimpleComplex\Config\Exception\RuntimeException;

/**
 * Sectioned configuration using .ini files as source,
 * and PSR-16 cache as store.
 *
 * Parses .ini sections, but arranges sections internally as mere prefixes
 * to keys; setters and getters concat section and key (around a delimiter).
 *
 * Best for contexts where
 * -----------------------
 * one expects to access keys of diverse sections in a chaotic manner,
 * but still want the organisational benefit of sections; many but exact
 * cache reads.
 *
 * Constructor returns effectively identical instance on second call, given
 * the same arguments; an instance is basically a wrapped cache store.
 *
 * Requires and uses [section]s in .ini files.
 *
 * @see \SimpleComplex\Locale\LocaleText
 *      For example of use.
 *
 * @property-read string $name
 * @property-read bool $useSourceSections
 * @property-read bool $escapeSourceKeys
 * @property-read bool $parseTyped
 * @property-read string $sectionKeyDelimiter
 * @property-read array $paths
 *      Copy, to secure read-only status.
 * @property-read array $fileExtensions
 * @property-read \SimpleComplex\Cache\ManageableCacheInterface $cacheStore
 *
 * @package SimpleComplex\Config
 */
class IniSectionedFlatConfig extends IniConfigBase implements SectionedConfigInterface
{
    // SectionedConfigInterface.------------------------------------------------

    /**
     * Fetches a configuration variable from cache.
     *
     * Does _not_ support wildcard * for arg key.
     *
     * @see SectionedConfigInterface::get()
     *
     * @inheritdoc
     */
    public function get(string $section, string $key, $default = null)
    {
        return $this->cacheStore->get($section . $this->sectionKeyDelimiter . $key, $default);
    }

    /**
     * Sets a configuration variable; in cache, not .ini file.
     *
     * @see SectionedConfigInterface::set()
     *
     * @inheritdoc
     */
    public function set(string $section, string $key, $value) : bool
    {
        $concat = $section . $this->sectionKeyDelimiter . $key;
        if (!ConfigKey::validate($concat)) {
            throw new InvalidArgumentException(
                'Arg section or key does not conform with .ini file and/or cache key requirements, concatted['
                . $concat . '].'
            );
        }
        return $this->cacheStore->set($concat, $value);
    }

    /**
     * Deletes a configuration variable; from cache, not .ini file.
     *
     * @see SectionedConfigInterface::delete()
     *
     * @inheritdoc
     */
    public function delete(string $section, string $key) : bool
    {
        if (!$this->cacheStore->delete($section . $this->sectionKeyDelimiter . $key)) {
            // Unlikely, but safer.
            throw new RuntimeException(
                'Underlying cache store type[' . get_class($this->cacheStore)
                . '] failed to delete cache item, concatted[' . $section . $this->sectionKeyDelimiter . $key . '].'
            );
        }
        return true;
    }

    /**
     * Retrieves multiple config items by their unique section+keys, from cache.
     *
     * @see SectionedConfigInterface::getMultiple()
     *
     * @inheritdoc
     */
    public function getMultiple(string $section, $keys, $default = null)
    {
        $concatted = [];
        foreach ($keys as $key) {
            $concatted[] = $section . $this->sectionKeyDelimiter . $key;
        }
        return $this->cacheStore->getMultiple($concatted, $default);
    }

    /**
     * Persists a set of section+key => value pairs; in the cache,
     * not .ini file.
     *
     * @see SectionedConfigInterface::setMultiple()
     *
     * @inheritdoc
     */
    public function setMultiple(string $section, $values) : bool
    {
        $concatted = [];
        foreach ($values as $key => $value) {
            $concatted[$section . $this->sectionKeyDelimiter . $key] = $value;
        }
        return $this->cacheStore->setMultiple($concatted);
    }

    /**
     * Check if a configuration item is set; in cache store, not (necessarily)
     * in .ini file.
     *
     * Does _not_ support wildcard * for arg key.
     *
     * @see SectionedConfigInterface::has()
     *
     * @inheritdoc
     */
    public function has(string $section, string $key) : bool
    {
        return $this->cacheStore->has($section . $this->sectionKeyDelimiter . $key);
    }

    /**
     * Does nothing.
     *
     * @see SectionedConfigInterface::remember()
     *
     * @inheritdoc
     *
     * @return null
     */
    public function remember(string $section)
    {
        return null;
    }

    /**
     * Does nothing.
     *
     * @see SectionedConfigInterface::forget()
     *
     * @inheritdoc
     */
    public function forget(string $section) /*: void*/
    {
    }


    // Override IniConfigBase.--------------------------------------------------

    /**
     * Whether to require and use [section]s of .ini file, or ignore them.
     *
     * @var bool
     */
    protected $useSourceSections = true;

    /**
     * Concat section and key inside setters and getters (with a delimiter),
     * and on the cache-level (cache key becomes section+delimiter+key).
     *
     * @var string
     */
    protected $sectionKeyDelimiter = '_-_';

    /**
     * Paths to where configuration .ini-files reside.
     *
     * Base configuration should work in dev/test environments.
     * Overriding configuration should consist of productions settings.
     *
     * Relative path is relative to document root.
     *
     * @var string[]
     */
    const PATH_DEFAULTS = [
        'base' => '../conf/ini-sectioned-flat/base',
        'override' => '../conf/ini-sectioned-flat/operations',
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
     * Doesn't specialize parent constructor, nor offers other constructor.
     *
     * @see IniConfigBase::__construct()
     */
}
