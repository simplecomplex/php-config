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
use SimpleComplex\Cache\BackupCacheInterface;
use SimpleComplex\Config\Exception\LogicException;
use SimpleComplex\Config\Exception\InvalidArgumentException;
use SimpleComplex\Config\Exception\OutOfBoundsException;
use SimpleComplex\Config\Exception\ConfigurationException;
use SimpleComplex\Config\Exception\RuntimeException;

/**
 * Base class for configuration classes using .ini files as source,
 * and PSR-16 Simple Cache (+ ManageableCacheInterface) as store.
 *
 * See example .ini file in: [package dir]/config-ini/example.ini.
 *
 * @property-read string $name
 * @property-read bool $useSourceSections
 * @property-read bool $escapeSourceKeys
 * @property-read bool $parseTyped
 * @property-read string|null $sectionKeyDelimiter
 * @property-read array $paths
 *      Copy, to secure read-only status.
 * @property-read array $fileExtensions
 * @property-read ManageableCacheInterface|BackupCacheInterface $cacheStore
 *      Is ManageableCacheInterface, may additionally be BackupCacheInterface.
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
     * Whether to expect and escape/unescape illegal .ini key names.
     *
     * Illegal .ini key names are: null, yes, no, true, false, on, off, none.
     *
     * Not exposed as constructor parameter because extending classes must
     * define whether that property is fixed or variable.
     *
     * @var bool
     */
    protected $escapeSourceKeys = false;

    /**
     * Whether to type values null|true|false|N|N.N.
     *
     * Not exposed as constructor parameter because extending classes must
     * define whether that property is fixed or variable.
     *
     * @var bool
     */
    protected $parseTyped = true;

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
        'escapeSourceKeys',
        'parseTyped',
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
            /** @var CacheBroker $cache_broker */
            $cache_broker = $container->get('cache-broker');
        } else {
            $cache_broker_class = static::CLASS_CACHE_BROKER;
            /** @var CacheBroker $cache_broker */
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
     * Reads and parses all .ini files found in instance var paths.
     *
     * @see Utils::resolvePath()
     * @see Utils::PathFileList()
     * @see Utils::parseIniString()
     *
     * @param bool $allowNone
     *      Falsy: throws ConfigurationException is no settings found at all.
     *
     * @return array
     *
     * @throws ConfigurationException
     *      A path doesn't exist or isn't directory.
     *      Using source sections, an .ini file doesn't declare a [section]
     *      before flat vars.
     *      No configuration item found at all; unless truthy arg allowNone.
     * @throws \Throwable
     *      Propagated.
     */
    public function readFromSources($allowNone = false) : array
    {
        $utils = Utils::getInstance();

        $collection = [];
        $n_files = 0;
        foreach ($this->paths as $path_name => $path) {
            if (!$path) {
                continue;
            }
            // Convert path to absolute if required, and check that it exists.
            $absolute_path = $utils->resolvePath($path);
            if (!file_exists($absolute_path)) {
                throw new ConfigurationException(
                    'The ' . (!ctype_digit('' . $path_name) ? ('\'' . $path_name . '\'') : ('index[' . $path_name . ']'))
                    . ' path doesn\'t exist, path[' . $absolute_path . ']'
                );
            }
            if (!is_dir($absolute_path)) {
                throw new ConfigurationException(
                    'The ' . (!ctype_digit('' . $path_name) ? ('\'' . $path_name . '\'') : ('index[' . $path_name . ']'))
                    . ' path is not a directory, path[' . $absolute_path . ']'
                );
            }
            // Find all .ini files in the path, recursively.
            $files = (new PathFileList($absolute_path, $this->fileExtensions))->getArrayCopy();
            if ($files) {
                // Parse all .ini files in the path.
                $settings_in_path = [];
                foreach ($files as $path_file) {
                    ++$n_files;
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
                                if ($this->escapeSourceKeys) {
                                    $ini = $utils->escapeIniKeys($ini);
                                }
                                $settings_in_file = $utils->parseIniString($ini, true, $this->parseTyped);
                                if ($this->escapeSourceKeys) {
                                    $utils->unescapeIniKeys($settings_in_file, true);
                                }
                                $settings_in_path = array_merge_recursive($settings_in_path, $settings_in_file);
                            }
                        }
                    } else {
                        $ini = trim(file_get_contents($path_file));
                        if ($this->escapeSourceKeys) {
                            $ini = $utils->escapeIniKeys($ini);
                        }
                        $settings_in_file = $utils->parseIniString($ini, false, $this->parseTyped);
                        if ($this->escapeSourceKeys) {
                            $utils->unescapeIniKeys($settings_in_file);
                        }
                        $settings_in_path = array_merge_recursive($settings_in_path, $settings_in_file);
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
        if (!$allowNone && !$collection) {
            if (!$n_files) {
                throw new ConfigurationException(
                    'Found no configuration files at all.'
                );
            }
            throw new ConfigurationException(
                'Found no configuration item at all.'
            );
        }
        return $collection;
    }

    /**
     * Builds fresh configuration from all .ini files in the base and override
     * paths (or any number of paths, if the concrete config class allows that).
     *
     * If current configuration isn't empty, and the internal cache store
     * supports backup procedures, the fresh configuration will be built as a
     * cache 'candidate' - and will only be used if successful (.ini parsing);
     * safe mode, really.
     * Otherwise current configuration cache will be cleared, before the new
     * is build.
     *
     * @see IniConfigBase::readFromSources()
     * @see \Psr\SimpleCache\CacheInterface::setMultiple()
     *
     * @param bool
     *
     * @return bool
     *      False: no configuration variables found in .ini files of the paths.
     *
     * @throws ConfigurationException
     *      Propagated, see readFromSources().
     * @throws \Throwable
     *      Propagated, from cache store.
     */
    public function refresh() : bool
    {
        $empty = $this->cacheStore->isEmpty();

        // Use (safe mode) cache 'candidate' procedure?
        $build_cache_candidate = !$empty && $this->cacheStore instanceof BackupCacheInterface;

        if (!$build_cache_candidate) {
            $this->cacheStore->clear();
        } else {
            $this->cacheStore->setCandidate();
        }

        // Load all variables from .ini file sources.
        try {
            $collection = $this->readFromSources();
        } catch (\Throwable $xc) {
            // Fail gracefully, if:
            // + building via cache candidate (safe mode, sort of)
            // + it's an ini parse error
            // + there's a logger
            if (
                $build_cache_candidate
                && $xc instanceof \SimpleComplex\Utils\Exception\ParseIniException
            ) {
                $container = Dependency::container();
                if ($container->has('logger')) {
                    /** @var \Psr\Log\LoggerInterface $logger */
                    $logger = $container->get('logger');
                    if ($container->has('inspector')) {
                        /** @var \SimpleComplex\Inspect\Inspect $inspector */
                        $inspector = $container->get('inspector');
                        $logger->warning(
                            '' . $inspector->trace($xc, [
                                // Increase string truncation.
                                'truncate' => 50000,
                            ])
                        );
                    } else {
                        $logger->warning($xc->getMessage(), [
                            'exception' => $xc,
                        ]);
                    }
                    // CLI mode: echo description of the situation.
                    if (\SimpleComplex\Utils\CliEnvironment::cli()) {
                        (new \SimpleComplex\Utils\CliEnvironment())->echoMessage(
                            $xc->getMessage()
                            . "\n" . 'Failed to build from sources, however existing cache prevails.'
                            . ' The incident was logged.',
                            'warning'
                        );
                    }
                    return false;
                }
            }
            throw $xc;
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

            if ($build_cache_candidate) {
                $this->cacheStore->promoteCandidate('before-refresh_' . date('Y-m-d_His'));
            }

            return true;
        }
        return false;
    }

    /**
     * Export from sources.
     *
     * Exporting from cache isn't possible because cache has no index;
     * doesn't know which sections and keys exist, unless asked specifically.
     *
     * @see Utils::resolvePath()
     *
     * @param string $targetFile
     *      Path and filename; the path must exist already.
     *      Relative is relative to document root.
     * @param array $options {
     *      @var string $format
     *          Default, and the only currently supported: JSON
     *      @var bool $unescaped
     *          (JSON) don't escape slash, tag, quotes, ampersand, unicode chars.
     *      @var bool $pretty
     *          (JSON) pretty-print.
     * }
     *
     * @return bool
     *      Creates/overwrites arg targetFile.
     *
     * @throws InvalidArgumentException
     *      Arg targetFile empty, or path part doesn't exist/isn't a directory.
     * @throws \TypeError
     *      Arg options bucket invalid value type.
     * @throws RuntimeException
     *      Failure to encode as format.
     *      Failure to write to file.
     * @throws \Exception
     *      Propagated, various kinds, from Utils::resolvePath().
     */
    public function export(string $targetFile, array $options = []) /*: void*/
    {
        $utils = Utils::getInstance();

        if (!$targetFile) {
            throw new InvalidArgumentException('Arg targetFile is empty.');
        }
        $target_file = basename($targetFile);
        $target_path = $utils->resolvePath(
            dirname($targetFile)
        );
        if (!file_exists($target_path)) {
            throw new InvalidArgumentException(
                'Arg targetFile path doesn\'t exist, targetFile[' . $targetFile . '], path[' . $target_path . '].');
        }
        if (!is_dir($target_path)) {
            throw new InvalidArgumentException(
                'Arg targetFile path is not a directory, targetFile[' . $targetFile . '], path[' . $target_path . '].');
        }

        $collection = $this->readFromSources();

        if (!empty($options['format'])) {
            if (!is_string($options['format'])) {
                throw new \TypeError('Arg options bucket format must be string or empty.');
            }
            switch ($options['format']) {
                case 'JSON':
                case 'json':
                    $format = 'JSON';
                    break;
                default:
                    throw new InvalidArgumentException(
                        'Arg options bucket format not supported, format[' . $options['format'] . '].'
                    );
            }
        } else {
            $format = 'JSON';
        }
        switch ($format) {
            case 'JSON':
                $unescaped = !empty($options['unescaped']);
                $pretty = !empty($options['pretty']);
                if (!$unescaped && !$pretty) {
                    $flags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
                } elseif (!$unescaped) {
                    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
                } else {
                    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
                }
                $encoded = json_encode($collection, $flags);
                break;
            default:
                throw new LogicException('Algo error, unsupported format[' . $options['format'] . '].');
        }
        if (!is_string($encoded)) {
            throw new RuntimeException('Failed to encode as ' . $format . '.');
        }
        if (!@file_put_contents($target_path . '/' . $target_file, $encoded)) {
            throw new RuntimeException('Failed to write to file[' . $target_path . '/' . $target_file . '].');
        }
        return true;
    }
}
