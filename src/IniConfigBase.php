<?php
/**
 * SimpleComplex PHP Config
 * @link      https://github.com/simplecomplex/php-config
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-config/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Config;

use SimpleComplex\Utils\Explorable;
use SimpleComplex\Utils\Utils;
use SimpleComplex\Utils\Dependency;
use SimpleComplex\Utils\PathFileList;
use SimpleComplex\Cache\CacheBroker;
use SimpleComplex\Cache\ManageableCacheInterface;
use SimpleComplex\Config\Exception\LogicException;
use SimpleComplex\Config\Exception\InvalidArgumentException;
use SimpleComplex\Config\Exception\OutOfBoundsException;
use SimpleComplex\Config\Exception\ConfigurationException;
use SimpleComplex\Config\Exception\RuntimeException;

/**
 * Base class for configuration classes using .ini files as source,
 * and PSR-16 Simple Cache (+ ManageableCacheInterface) as store.
 *
 * @property-read string $name
 * @property-read bool $useSourceSections
 * @property-read string|null $sectionKeyDelimiter
 * @property-read array $paths
 *      Copy, to secure read-only status.
 * @property-read array $fileExtensions
 * @property-read ManageableCacheInterface $cacheStore
 *
 * @see \Psr\SimpleCache\CacheInterface
 * @see ManageableCacheInterface
 *
 * @package SimpleComplex\Config
 */
abstract class IniConfigBase extends Explorable
{
    /**
     * @var string
     */
    const CLASS_CACHE_BROKER = CacheBroker::class;

    /**
     * @var string
     */
    protected $name;

    /**
     * Config's cache store.
     *
     * @var ManageableCacheInterface
     */
    protected $cacheStore;

    /**
     * Whether to require and use [section]s of .ini file, or ignore them.
     *
     * Not exposed as constructor parameter because extending classes must
     * define whether that property is fixed or variable.
     *
     * @var bool
     */
    protected $useSourceSections = false;

    /**
     * Concat section and key inside setters and getters (with a delimiter),
     * and on the cache-level (cache key becomes section+delimiter+key).
     *
     * Only relevant when useSourceSections:true.
     *
     * Not exposed as constructor parameter because extending classes must
     * define whether that property is fixed or variable.
     *
     * @see IniSectionedFlatConfig
     *
     * @var string|null
     */
    protected $sectionKeyDelimiter;

    /**
     * In the base class there are no path defaults.
     *
     * Relative path is relative to document root.
     *
     * @var string[]
     */
    const PATH_DEFAULTS = [];

    /**
     * The base class allows any paths; any names and number of.
     *
     * Relative path is relative to document root.
     *
     * @var string[]
     */
    protected $paths = [];

    /**
     * @var array
     */
    const FILE_EXTENSTIONS = [
        'ini',
    ];

    /**
     * @var array
     */
    protected $fileExtensions;


    // Explorable.--------------------------------------------------------------

    /**
     * List of names of members (private, protected or public) which should be
     * exposed as accessibles in count()'ing and foreach'ing.
     *
     * @var array
     */
    protected $explorableIndex = [
        'name',
        'useSourceSections',
        'sectionKeyDelimiter',
        'paths',
        'fileExtensions',
        'cacheStore',
    ];

    /**
     * @param string $name
     *
     * @return mixed
     *
     * @throws OutOfBoundsException
     *      If no such instance property.
     */
    public function __get($name)
    {
        switch ($name) {
            case 'paths':
            case 'fileExtensions':
                // Return copy to secure read-only status.
                $v = $this->{$name};
                return $v;
            default:
                if (in_array($name, $this->explorableIndex, true)) {
                    return $this->{$name};
                }
        }
        throw new OutOfBoundsException(get_class($this) . ' instance exposes no property[' . $name . '].');
    }

    /**
     * @param string $name
     * @param mixed|null $value
     *
     * @return void
     *
     * @throws OutOfBoundsException
     *      If no such instance property.
     * @throws RuntimeException
     *      If that instance property is read-only.
     */
    public function __set($name, $value) /*: void*/
    {
        if (in_array($name, $this->explorableIndex, true)) {
            throw new RuntimeException(get_class($this) . ' instance property[' . $name . '] is read-only.');
        }
        throw new OutOfBoundsException(get_class($this) . ' instance exposes no property[' . $name . '].');
    }

    /**
     * @see \Iterator::current()
     * @see Explorable::current()
     *
     * @return mixed
     */
    public function current()
    {
        // Override to facilitate direct call to __get(); cheaper.
        return $this->__get(current($this->explorableIndex));
    }


    // Business.----------------------------------------------------------------

    /**
     * Create or load configuration store.
     *
     * Will return effectively identical instance on second call, when given
     * the same arguments; an instance is basically a wrapped cache store.
     *
     * Cache store name is 'config_[arg name]'.
     *
     * @uses CacheBroker::getStore()
     *
     * @param string $name
     * @param string[] $paths
     *      Relative path is relative to document root.
     *
     * @throws InvalidArgumentException
     *      Invalid arg $name.
     * @throws \TypeError
     *      A path bucket value isn't string.
     * @throws ConfigurationException
     *      CacheBroker returns cache store which isn't ManageableCacheInterface.
     *      If no (arg paths * default paths) path is non-empty.
     *      Propagated, if a path doesn't exist or isn't directory.
     * @throws LogicException
     *      Current (or parent) class declares a fixed set of path names
     *      but doesn't declare equivalent PATH_DEFAULTS.
     * @throws \Throwable
     *      Propagated.
     */
    public function __construct(string $name, array $paths = [])
    {
        if (!ConfigKey::validate($name)) {
            throw new InvalidArgumentException('Arg name is not valid, name[' . $name . '].');
        }
        $this->name = $name;

        // Allow extending class constructor to define file extensions.
        if (!$this->fileExtensions) {
            $this->fileExtensions = static::FILE_EXTENSTIONS;
        }

        $container = Dependency::container();
        // We need a cache store, no matter what.
        if ($container->has('cache-broker')) {
            $cache_broker = $container->get('cache-broker');
        } else {
            $cache_broker_class = static::CLASS_CACHE_BROKER;
            $cache_broker = new $cache_broker_class();
        }
        $this->cacheStore = $cache_broker->getStore('config_' . $name);
        // The cache store must implement ManageableCacheInterface.
        if (!($this->cacheStore instanceof ManageableCacheInterface)) {
            throw new ConfigurationException(
                'Cache store must implement ManageableCacheInterface, saw type['
                . (!is_object($this->cacheStore) ? gettype($this->cacheStore) : get_class($this->cacheStore)) . '].'
            );
        }
        // Cache should live forever.
        // And setter/getter arg ttl should be ignored (we don't pass it anyway).
        // In effect: time-to-live should be ignored complete
        $this->cacheStore->setTtlIgnore(true);
        $this->cacheStore->setTtlDefault(ManageableCacheInterface::TTL_NONE);

        // Fixed or unlimited set of paths.
        if ($this->paths) {
            $fixed_path_range = true;
            // Use class' path names.
            $path_names = array_keys($this->paths);
        } else {
            $fixed_path_range = false;
            // Use arg paths' path names.
            $path_names = array_keys($paths);
        }
        $n_non_empty_paths = 0;
        foreach ($path_names as $path_name) {
            if (!empty($paths[$path_name])) {
                if (!is_string($paths[$path_name])) {
                    throw new \TypeError('Arg array bucket paths[' . $path_name . '] type['
                        . (!is_object($paths[$path_name]) ? gettype($paths[$path_name]) :
                            get_class($paths[$path_name])) . '] is not string.');
                }
                $this->paths{$path_name} = $paths[$path_name];
                if ($paths[$path_name]) {
                    ++$n_non_empty_paths;
                }
            } elseif ($fixed_path_range) {
                if (!isset(static::PATH_DEFAULTS[$path_name])) {
                    throw new LogicException(
                        'Cache store must implement ManageableCacheInterface, saw type['
                        . (!is_object($this->cacheStore) ? gettype($this->cacheStore) : get_class($this->cacheStore)) . '].'
                    );
                }
                $this->paths{$path_name} = static::PATH_DEFAULTS[$path_name];
                if ($this->paths{$path_name}) {
                    ++$n_non_empty_paths;
                }
            }
        }
        if (!$n_non_empty_paths) {
            throw new ConfigurationException(
                'At least one path must be non-empty, default paths[' . join(', ', static::PATH_DEFAULTS)
                . '], arg paths[' . join(', ', $paths) . '].'
            );
        }

        // Don't import from .ini-files if our cache store has items.
        if (!$this->cacheStore->isEmpty()) {
            return;
        }

        // Do attempt to import.
        $this->refresh();
    }

    /**
     * Flushes the cache store and loads fresh configuration from all .ini files
     * in the base and override paths.
     *
     * @see Utils::resolvePath()
     * @see Utils::PathFileList()
     * @see Utils::parseIniString()
     * @see \Psr\SimpleCache\CacheInterface::setMultiple()
     *
     * @return bool
     *      False: no configuration variables found in .ini files of the paths.
     *
     * @throws ConfigurationException
     *      A path doesn't exist or isn't directory.
     *      No configuration item found at all.
     *      Using source sections, an .ini file doesn't declare a [section]
     *      before flat vars.
     * @throws \Throwable
     *      Propagated.
     */
    public function refresh() : bool
    {
        $utils = Utils::getInstance();

        // Clear cache first, as promised.
        $this->cacheStore->clear();

        // Load all variables from .ini file sources.
        $collection = [];
        foreach ($this->paths as $path_name => $path) {
            if (!$path) {
                continue;
            }
            // Convert path to absolute if required, and check that it exists.
            $absolute_path = $utils->resolvePath($path);
            if (!file_exists($absolute_path)) {
                throw new ConfigurationException(
                    'The \'' . $path_name . '\' path doesn\'t exist, path[' . $absolute_path . ']'
                );
            }
            if (!is_dir($absolute_path)) {
                throw new ConfigurationException(
                    'The \'' . $path_name . '\' path is not a directory, path[' . $absolute_path . ']'
                );
            }
            // Find all .ini files in the path, recursively.
            $files = (new PathFileList($absolute_path, $this->fileExtensions))->getArrayCopy();
            if ($files) {
                // Parse all .ini files in the path.
                $settings_in_path = [];
                foreach ($files as $path_file) {
                    if ($this->useSourceSections) {
                        $ini = trim(
                            file_get_contents($path_file)
                        );
                        if ($ini) {
                            // Check that the whole configuration begins with a [section].
                            // Remove comments and leading empty lines.
                            $ini = ltrim(
                                preg_replace(
                                    '/\n;[^\n]*/m',
                                    "\n",
                                    "\n" . str_replace("\r", '', $ini)
                                )
                            );
                            if (trim($ini)) {
                                if (!preg_match('/^\[/', $ini)) {
                                    throw new ConfigurationException(
                                        'Using source sections, an .ini file must declare a [section] before flat vars,'
                                        . 'file[' . $path_file . '].'
                                    );
                                }
                                // Union; two files within same dir shouldn't declare the
                                // the same vars.
                                // But if they do, the latter will rule.
                                $settings_in_path += $utils->parseIniString($ini, true, true);
                            }
                        }
                    } else {
                        $settings_in_path += $utils->parseIniFile($path_file, false, true);
                    }
                }
                if ($settings_in_path) {
                    if (!$collection) {
                        $collection =& $settings_in_path;
                        unset($settings_in_path);
                    } else {
                        // Let variables of latter path override settings
                        // of previous paths.
                        $collection = array_replace_recursive(
                            $collection,
                            $settings_in_path
                        );
                    }
                }
            }
        }
        if (!$collection) {
            throw new ConfigurationException(
                'Found no configuration item at all.'
            );
        }

        // Pass to cache.
        if ($collection) {
            if ($this->useSourceSections && $this->sectionKeyDelimiter) {
                // Flatten sections+keys.
                $concatted = [];
                foreach ($collection as $section => $arr_subs) {
                    foreach ($arr_subs as $key => $value) {
                        $concat_key = $section . $this->sectionKeyDelimiter . $key;
                        // Check that the final key isn't too long.
                        if (!ConfigKey::validate($concat_key)) {
                            throw new ConfigurationException(
                                'Concatted section+delimiter+key key is not valid, concatted[' . $concat_key . '].'
                            );
                        }
                        $concatted[$concat_key] = $value;
                    }
                }
                $collection =& $concatted;
            }

            if (!$this->cacheStore->setMultiple($collection)) {
                // Unlikely, but safer.
                throw new RuntimeException(
                    'Underlying cache store type[' . get_class($this->cacheStore)
                    . '] failed to set cache items loaded from .ini file(s).'
                );
            }
            return true;
        }
        return false;
    }
}
