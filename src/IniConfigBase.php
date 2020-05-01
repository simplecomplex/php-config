<?php
/**
 * SimpleComplex PHP Config
 * @link      https://github.com/simplecomplex/php-config
 * @copyright Copyright (c) 2017-2019 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-config/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Config;

use SimpleComplex\Utils\Explorable;
use SimpleComplex\Utils\Utils;
use SimpleComplex\Utils\Dependency;
use SimpleComplex\Utils\PathList;
use SimpleComplex\Utils\CliEnvironment;
use SimpleComplex\Cache\CacheBroker;
use SimpleComplex\Cache\Interfaces\ManageableCacheInterface;
use SimpleComplex\Cache\Interfaces\BackupCacheInterface;
use SimpleComplex\Config\Exception\LogicException;
use SimpleComplex\Config\Exception\InvalidArgumentException;
use SimpleComplex\Config\Exception\OutOfBoundsException;
use SimpleComplex\Config\Exception\ConfigException;
use SimpleComplex\Utils\Exception\KeyNonUniqueException;
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
     * Use cache that allows long keys; 128 chars instead of 64.
     *
     * @see ConfigKeyLong
     *
     * @var bool
     */
    const CACHE_KEY_LONG = false;

    /**
     * Illegal config store names, and why they are illegal.
     *
     * @var string[]
     */
    const NAME_ILLEGALS = [
        'example' => 'examples of .ini files have extension .[store-name].example.ini',
        'ini-source-packages' =>
            'lists of packages providing .ini files have extension .[store-name].ini-source-packages.ini',
    ];

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
     * @var null|string
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
     * Paths received as constructor arg paths;
     * not resolved to absolute paths yet.
     *
     * Constructor sets it to array.
     * definePaths() sets it to null.
     *
     * @see IniConfigBase::definePaths()
     *
     * @var array|null
     */
    protected $pathsPassed;

    /**
     * Defaults to use only one extension: [config store name].ini.
     *
     * The [config store name] part (no brackets, nb) allow configuration
     * files of different stores to 'live' in the same paths.
     *
     * @var string[]
     */
    protected $fileExtensions;

    /**
     * Discovery mode filename of .ini source packages file.
     *
     * The file must be placed in the store's (verbatim) 'base' path directory.
     * See this package's example file
     * config-ini/config.global.ini-source-packages.example.ini.
     *
     * Must contain '[store-name]', which gets replaced by instance $name.
     *
     * @see IniConfigBase::readFromSources()
     *
     * @var string
     */
    const DISCOVERY_SOURCE_PACKAGES_FILENAME = 'config.[store-name].ini-source-packages.ini';

    /**
     * Discovery mode requires that every .ini file of a source package
     * is placed in a directory by this name.
     *
     * Empty if no such requirement.
     *
     * @see PathList::includeParents()
     *
     * @see IniConfigBase::readFromSources()
     *
     * @var string
     */
    const DISCOVERY_INI_PARENT_DIRNAME = 'config-ini';


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
                if (is_array($this->pathsPassed)) {
                    $this->definePaths();
                }
                // Return copy to secure read-only status.
                $v = $this->paths;
                return $v;
            case 'fileExtensions':
                // Return copy to secure read-only status.
                $v = $this->fileExtensions;
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
     * Cache store name is 'config.[arg name]'.
     * Cache store type is 'persistent'; no end of life.
     *
     * @uses CacheBroker::getStore()
     * @uses CacheBroker::CACHE_PERSISTENT
     *
     * @see IniConfigBase::definePaths()
     *
     * @param string $name
     * @param string[] $paths
     *      Relative path is relative to document root.
     *
     * @throws InvalidArgumentException
     *      Invalid arg $name.
     * @throws \TypeError
     *      A path bucket value isn't string.
     * @throws ConfigException
     *      CacheBroker returns cache store which isn't ManageableCacheInterface.
     *      Propagated; if no path (arg paths * default paths) is non-empty.
     * @throws LogicException
     *      Propagated; current (or parent) class declares a fixed set of path
     *      names but doesn't declare equivalent PATH_DEFAULTS.
     * @throws \InvalidArgumentException|\Psr\SimpleCache\InvalidArgumentException
     * @throws \Throwable
     *      Propagated.
     */
    public function __construct(string $name, array $paths = [])
    {
        if (!ConfigKey::validate($name)) {
            throw new InvalidArgumentException('Arg name[' . $name . '] is invalid.');
        }
        if (isset(static::NAME_ILLEGALS[$name])) {
            throw new InvalidArgumentException(
                'Arg name[' . $name . '] is illegal because '. static::NAME_ILLEGALS[$name] . '.'
            );
        }
        $this->name = $name;

        // Allow extending class constructor to define file extensions.
        if (!$this->fileExtensions) {
            // The [config store name] part allow configuration files
            // of different stores to 'live' in the same paths.
            $this->fileExtensions = [
                $name . '.ini',
            ];
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
        // Use a persistent (time-to-live forever + ignore arg ttl) cache class.
        $this->cacheStore = $cache_broker->getStore(
            'config.' . $name,
            !static::CACHE_KEY_LONG ? CacheBroker::CACHE_PERSISTENT : CacheBroker::CACHE_KEY_LONG_PERSISTENT
        );
        // The cache store must implement ManageableCacheInterface.
        if (!($this->cacheStore instanceof ManageableCacheInterface)) {
            throw new ConfigException(
                'Cache store must implement ManageableCacheInterface, saw type['
                . Utils::getType($this->cacheStore) . '].'
            );
        }

        // Memorize arg paths for later; to be settled on demand,
        // by definePaths().
        $this->pathsPassed = $paths;

        // Don't import from .ini-files if our cache store
        // is regenerated and has items.
        if (!$this->cacheStore->isNew() && !$this->cacheStore->isEmpty()) {
            return;
        }

        // Import from sources and write to cache.
        $this->refresh();
    }

    /**
     *
     *
     * @return void
     *
     * @throws \TypeError
     *      A path bucket value isn't string.
     * @throws ConfigException
     *      If no path (constructor arg paths * default paths) is non-empty.
     *      Propagated, if a path doesn't exist or isn't directory.
     * @throws LogicException
     *      Current (or parent) class declares a fixed set of path names
     *      but doesn't declare equivalent PATH_DEFAULTS.
     */
    protected function definePaths() /*: void*/
    {
        // Already settled?
        if ($this->pathsPassed === null) {
            return;
        }
        $paths =& $this->pathsPassed;

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
                    throw new \TypeError(
                        'Construcor arg array bucket paths[' . $path_name . '] type['
                        . Utils::getType($paths[$path_name]) . '] is not string.'
                    );
                }
                $this->paths{$path_name} = $paths[$path_name];
                if ($paths[$path_name]) {
                    ++$n_non_empty_paths;
                }
            } elseif ($fixed_path_range) {
                if (!isset(static::PATH_DEFAULTS[$path_name])) {
                    throw new LogicException(
                        'Cache store must implement ManageableCacheInterface, saw type['
                        . Utils::getType($this->cacheStore) . '].'
                    );
                }
                $this->paths{$path_name} = static::PATH_DEFAULTS[$path_name];
                if ($this->paths{$path_name}) {
                    ++$n_non_empty_paths;
                }
            }
        }
        if (!$n_non_empty_paths) {
            throw new ConfigException(
                'At least one path must be non-empty, default paths[' . join(', ', static::PATH_DEFAULTS)
                . '], construcor arg paths[' . join(', ', $paths) . '].'
            );
        }

        // Flag that paths now are defined.
        unset($paths);
        $this->pathsPassed = null;
    }

    /**
     * Reads and parses all .ini files found in instance var paths.
     *
     * @see Utils::resolvePath()
     * @see Utils::PathList()
     * @see Utils::parseIniString()
     *
     * @param bool $allowNone
     *      Falsy: throws ConfigException is no settings found at all.
     * @param bool $verbose
     *      CLI mode only, list source .ini files used.
     *
     * @return array
     *
     * @throws ConfigException
     *      A path doesn't exist or isn't directory.
     *      Using source sections, an .ini file doesn't declare a [section]
     *      before flat vars.
     *      No configuration item found at all; unless truthy arg allowNone.
     * @throws \Throwable
     *      Propagated.
     */
    protected function readFromSources(bool $allowNone = false, bool $verbose = false) : array
    {
        // Settle paths, unless already done.
        if (is_array($this->pathsPassed)) {
            $this->definePaths();
        }

        $utils = Utils::getInstance();
        $cli_env = $verbose && CliEnvironment::cli() ? new CliEnvironment() : null;

        $collection = [];
        $n_files = 0;

        // Discovery mode: the base path contains a list of packages-by-vendor
        // which contain relevant ini-files, placed in a 'config-ini' dir.
        $ini_sources_file = null;
        if (!empty($this->paths['base'])) {
            $ini_sources_file = $utils->resolvePath(
                // ../conf/ini/base/config.global.ini-source-packages.ini
                $this->paths['base'] . '/'
                . str_replace('[store-name]', $this->name, static::DISCOVERY_SOURCE_PACKAGES_FILENAME)
            );
            if (file_exists($ini_sources_file) && is_file($ini_sources_file)) {
                $ini_sources = $utils->parseIniFile($ini_sources_file, true, true);
                if (!isset($ini_sources['packages-by-vendors'])) {
                    throw new ConfigException(
                        'Config discovery mode package list file[' . $ini_sources_file
                        . '] section \'packages-by-vendors\' is missing.'
                    );
                }
                if ($ini_sources['packages-by-vendors']) {
                    $vendor_path = $utils->documentRoot() . '/' . $utils->vendorDir();
                    $files = (new PathList(''))->includeExtensions($this->fileExtensions);
                    if (static::DISCOVERY_INI_PARENT_DIRNAME) {
                        $files->includeParents([static::DISCOVERY_INI_PARENT_DIRNAME]);
                    }
                    foreach ($ini_sources['packages-by-vendors'] as $vendor => $packages) {
                        if ($packages) {
                            $files->clear();
                            if ($packages === '*') {
                                $files->path($vendor_path . '/' . $vendor)->find();
                            }
                            elseif (is_array($packages)) {
                                foreach ($packages as $package) {
                                    $files->path($vendor_path . '/' . $vendor . '/' . $package)->find();
                                }
                            }
                            else {
                                throw new ConfigException(
                                    'Config discovery mode package list file[' . $ini_sources_file
                                    . '] section \'packages-by-vendors\' vendor key[' . $vendor
                                    . '] is neither wildcard string * nor array.'
                                );
                            }
                            if ($files->count()) {
                                $n_files += $files->count();
                                if ($verbose && $cli_env) {
                                    $cli_env->echoMessage(
                                        'Discovered .ini sources under vendor[' . $vendor . ']:' . "\n"
                                        . join("\n", $files->listDocumentRootReplaced())
                                    );
                                }
                                if (!$collection) {
                                    $collection = $this->readFromPath('- discovery mode -', $files);
                                }
                                else {
                                    // Let numerically indexed variables of latter
                                    // vendor _append_ to settings of previous vendor.
                                    // Allow competing associative keys (and sub-keys)
                                    // to override.
                                    $collection = $utils->arrayMergeRecursive(
                                        $collection,
                                        $this->readFromPath('- discovery mode -', $files)
                                    );
                                }
                            }
                            elseif ($verbose && $cli_env) {
                                $cli_env->echoMessage(
                                    'Discovered no .ini sources under vendor[' . $vendor . ']'
                                    . (!static::DISCOVERY_INI_PARENT_DIRNAME ? '.' :
                                        ' in dir(s) named[' . static::DISCOVERY_INI_PARENT_DIRNAME . '].')
                                );
                            }
                        }
                    }
                }
                elseif ($verbose && $cli_env) {
                    $cli_env->echoMessage(
                        'Discovered no .ini source vendors because empty packages-by-vendors section'
                        . ' in .ini source packages file[' . $ini_sources_file . '].'
                    );
                }
            }
            elseif ($verbose && $cli_env) {
                $cli_env->echoMessage(
                    'Discovered no .ini source vendors because no .ini source packages file['
                    . $ini_sources_file . '].'
                );
            }
        }

        // Ordinary path mode; 'base' and 'override', or custom (possibly even
        // numerically indexed) paths.
        foreach ($this->paths as $path_name => $path) {
            if (!$path) {
                continue;
            }
            // Convert path to absolute if required, and check that it exists.
            $absolute_path = $utils->resolvePath($path);
            if (!file_exists($absolute_path)) {
                throw new ConfigException(
                    'The ' . (!ctype_digit('' . $path_name) ? ('\'' . $path_name . '\'') : ('index[' . $path_name . ']'))
                    . ' path doesn\'t exist, path[' . $absolute_path . ']'
                );
            }
            if (!is_dir($absolute_path)) {
                throw new ConfigException(
                    'The ' . (!ctype_digit('' . $path_name) ? ('\'' . $path_name . '\'') : ('index[' . $path_name . ']'))
                    . ' path is not a directory, path[' . $absolute_path . ']'
                );
            }
            // Find all .[store name].ini files in the path, recursively.
            $files = (new PathList($absolute_path))->includeExtensions($this->fileExtensions)->find();
            if ($files->count()) {
                $n_files += $files->count();
                if ($verbose && $cli_env) {
                    $cli_env->echoMessage(
                        'Found .ini sources in path[' . $path_name . ']:' . "\n"
                        . join("\n", $files->listDocumentRootReplaced())
                    );
                }
                $settings_in_path = $this->readFromPath($path_name, $files);
                if ($settings_in_path) {
                    if (!$collection) {
                        $collection = $settings_in_path;
                    }
                    else {
                        // Let variables of latter path _override_ settings
                        // of previous paths, unconditionally.
                        $collection = array_replace_recursive(
                            $collection,
                            $settings_in_path
                        );
                    }
                }
            }
            elseif ($verbose && $cli_env) {
                $cli_env->echoMessage('Found no .ini sources in path[' . $path_name . '].');
            }
        }
        if (!$allowNone && !$collection) {
            if (!$n_files) {
                throw new ConfigException(
                    'Found no configuration files at all for config[' . $this->name
                    . '] when reading from sources for, looking for extensions[' . join(', ', $this->fileExtensions)
                    . ']' . (!$ini_sources_file ? '' : (' in packages defined by ini-source-packages file['
                        . $ini_sources_file . '] and'))
                    . ' in paths[' . join(', ', $this->paths) . '].'
                );
            }
            throw new ConfigException(
                'Found no configuration item at all for config[' . $this->name . '] when reading from sources.'
            );
        }
        return $collection;
    }

    /**
     * @see IniConfigBase::readFromSources()
     *
     * @param string|int $path_name
     * @param PathList $files
     *
     * @return array
     */
    protected function readFromPath($path_name, PathList $files)
    {
        $utils = Utils::getInstance();

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
                            throw new ConfigException(
                                'Using source sections, an .ini file must declare a [section] before flat vars,'
                                . 'file[' . $path_file . '].'
                            );
                        }
                        if ($this->escapeSourceKeys) {
                            $ini = $utils->escapeIniKeys($ini);
                        }
                        $settings_in_file = $utils->parseIniString($ini, true, $this->parseTyped);
                        if ($settings_in_file) {
                            if ($this->escapeSourceKeys) {
                                $utils->unescapeIniKeys($settings_in_file, true);
                            }
                            // Let numerically indexed variables of latter
                            // file _append_ to settings of previous file.
                            // Detect competing associative keys (and sub-keys)
                            // both having non-array value.
                            try {
                                foreach ($settings_in_file as $section => $list) {
                                    if (empty($settings_in_path[$section])) {
                                        $settings_in_path[$section] = $settings_in_file[$section];
                                    }
                                    else {
                                        $settings_in_path[$section] = $utils->arrayMergeUniqueRecursive(
                                            $settings_in_path[$section],
                                            $settings_in_file[$section]
                                        );
                                    }
                                }
                            }
                            catch (KeyNonUniqueException $xcptn) {
                                throw new ConfigException(
                                    'Competing associative keys (and sub-keys) both having non-array value'
                                    . ' are illegal within same path[' . $path_name . ']: '
                                    . $xcptn->getMessage()
                                );
                            }
                        }
                    }
                }
            }
            // Flat non-sectioned; sections ignored.
            else {
                $ini = trim(file_get_contents($path_file));
                if ($this->escapeSourceKeys) {
                    $ini = $utils->escapeIniKeys($ini);
                }
                $settings_in_file = $utils->parseIniString($ini, false, $this->parseTyped);
                if ($this->escapeSourceKeys) {
                    $utils->unescapeIniKeys($settings_in_file);
                }
                // Let numerically indexed variables of latter
                // file _append_ to settings of previous file.
                // Allow competing associative keys (and sub-keys)
                // to override (because we don't care; non-sectional
                // mode is toy mode).
                $settings_in_path = $utils->arrayMergeRecursive($settings_in_path, $settings_in_file);
            }
        }

        return $settings_in_path;
    }

    /**
     * Get all sections/keys.

     * @param bool $fromSources
     *      Truthy: read from sources paths' ini files.
     *
     * @return array
     *
     * @throws \Throwable
     *      Propagated.
     */
    public function getAll(bool $fromSources = false) : array
    {
        if (!$fromSources) {
            $collection = $this->cacheStore->export();
            if ($this->sectionKeyDelimiter) {
                $delim = $this->sectionKeyDelimiter;
                $un_delimited = [];
                foreach ($collection as $section_key => $value) {
                    $sctn_ky = explode($delim, $section_key);
                    $un_delimited[$sctn_ky[0]][$sctn_ky[1]] = $value;
                }
                $collection =& $un_delimited;
            }
        }
        else {
            $collection = $this->readFromSources();
        }
        ksort($collection);
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
     * @param bool $allowNone
     *      Falsy: throws ConfigException is no settings found at all.
     * @param bool $verbose
     *      CLI mode only, list source .ini files used.
     *
     * @return bool
     *      False: no configuration variables found in .ini files of the paths.
     *
     * @throws ConfigException
     *      Invalid section+delimiter+key key.
     *      Propagated, see readFromSources().
     * @throws \InvalidArgumentException|\Psr\SimpleCache\InvalidArgumentException
     * @throws \Throwable
     *      Propagated, from cache store.
     */
    public function refresh(bool $allowNone = false, bool $verbose = false) : bool
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
            $collection = $this->readFromSources($allowNone, $verbose);
        }
        catch (\Throwable $xc) {
            // Fail gracefully, if:
            // + building via cache candidate (safe mode, sort of)
            // + there's a logger
            if ($build_cache_candidate) {
                $container = Dependency::container();
                if ($container->has('logger')) {
                    /** @var \Psr\Log\LoggerInterface $logger */
                    $logger = $container->get('logger');
                    if ($container->has('inspect')) {
                        /** @var \SimpleComplex\Inspect\Inspect $inspect */
                        $inspect = $container->get('inspect');
                        $logger->warning(
                            '' . $inspect->trace($xc, [
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
                    if (CliEnvironment::cli()) {
                        (new CliEnvironment())->echoMessage(
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
                        if (!static::CACHE_KEY_LONG) {
                            if (!ConfigKey::validate($concat_key)) {
                                if (strlen($concat_key) > ConfigKey::VALID_LENGTH_MAX) {
                                    throw new ConfigException(
                                        'Concatted section+delimiter+key key length ' . strlen($concat_key) . ' exceeds max '
                                        . ConfigKey::VALID_LENGTH_MAX . ', concatted[' . $concat_key . '].'
                                    );
                                }
                                throw new ConfigException(
                                    'Concatted section+delimiter+key key is not valid, concatted[' . $concat_key . '].'
                                );
                            }
                        } elseif (!ConfigKeyLong::validate($concat_key)) {
                            if (strlen($concat_key) > ConfigKeyLong::VALID_LENGTH_MAX) {
                                throw new ConfigException(
                                    'Concatted section+delimiter+key key length ' . strlen($concat_key) . ' exceeds max '
                                    . ConfigKeyLong::VALID_LENGTH_MAX . ', concatted[' . $concat_key . '].'
                                );
                            }
                            throw new ConfigException(
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
     * Export from cache or sources.
     *
     * @see Utils::resolvePath()
     *
     * @param string $targetFile
     *      Path and filename; the path must exist already.
     *      Relative is relative to document root.
     * @param array $options {
     *      @var boolean $fromSources
     *          Falsy (default): export from cache.
     *          Truthy: export from sources paths' ini files.
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
     * @throws \Throwable
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

        $collection = $this->getAll(!empty($options['fromSources']));

        if ($this->useSourceSections) {
            // Fix that empty section must be object, not array; PHP json_encode()
            // encodes empty array as array, even in non-assoc mode.
            foreach ($collection as &$section) {
                if (!$section) {
                    $section = new \stdClass();
                }
            }
            // Iteration ref.
            unset($section);
        }

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
                $encoded = json_encode(
                    $collection,
                    (!$unescaped ? (JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) :
                        (JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                    | (!$pretty ? 0 : JSON_PRETTY_PRINT)
                );
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
