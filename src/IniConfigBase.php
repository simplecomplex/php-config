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
use SimpleComplex\Utils\PathFileList;
use SimpleComplex\Cache\CacheBroker;
use SimpleComplex\Cache\ManagableCacheInterface;
use SimpleComplex\Config\Exception\InvalidArgumentException;
use SimpleComplex\Config\Exception\OutOfBoundsException;
use SimpleComplex\Config\Exception\ConfigurationException;
use SimpleComplex\Config\Exception\RuntimeException;

/**
 * Helper for configuration classes using .ini files as source,
 * and PSR-16 Simple Cache (+ ManagableCacheInterface) as store.
 *
 * @property-read string $name
 * @property-read bool $useSourceSections
 * @property-read string $pathBase
 * @property-read string $pathOverride
 * @property-read ManagableCacheInterface $cacheStore
 *
 * @see \Psr\SimpleCache\CacheInterface
 * @see ManagableCacheInterface
 *
 * @package SimpleComplex\Config
 */
class IniConfigBase extends Explorable
{
    /**
     * @var string
     */
    protected $name;

    /**
     * Paths to where configuration .ini-files reside.
     *
     * Base configuration should work in dev/test environments.
     * Overriding configuration should consist of productions settings.
     */
    const PATH_DEFAULTS = [
        'base' => '../conf/ini/base',
        'override' => '../conf/ini/operations',
    ];

    /**
     * @var string[]
     */
    protected $paths = [
        'base' => '',
        'override' => '',
    ];

    /**
     * Whether to require and use [section]s of .ini file, or ignore them.
     *
     * @var bool
     */
    protected $useSourceSections = false;

    /**
     * Config's cache store.
     *
     * @var ManagableCacheInterface
     */
    protected $cacheStore;

    /**
     * @var Utils
     */
    protected $utils;


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
        'pathBase',
        'pathOverride',
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
        switch ('' . $name) {
            case 'pathBase':
            case 'pathOverride':
                return $this->paths['' . $name == 'pathBase' ? 'base' : 'override'];
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
     * @uses CacheBroker::getStore()
     *
     * @param string $name
     * @param array $options {
     *      @var string $pathBase = ''
     *          Empty: class default (PATH_BASE_DEFAULT) rules.
     *      @var string $pathOverride = ''
     *          Empty: class default (PATH_OVERRIDE_DEFAULT) rules.
     * }
     * @throws \TypeError
     * @throws InvalidArgumentException
     *      Bad value of an arg options bucket.
     * @throws ConfigurationException
     *      CacheBroker returns cache store which isn't ManagableCacheInterface.
     *      Propagated, if pathBase or pathOverride doesn't exist or isn't directory.
     * @throws \Throwable
     *      Propagated.
     */
    public function __construct(string $name, array $options = [])
    {
        if (!ConfigKey::validate($name)) {
            throw new InvalidArgumentException('Arg name is not valid, name[' . $name . '].');
        }
        $this->name = $name;

        // We need a cache store, no matter what.
        $this->cacheStore = CacheBroker::getInstance()->getStore($name);
        // The cache store must have an empty() method.
        if (!($this->cacheStore instanceof ManagableCacheInterface)) {
            throw new ConfigurationException(
                'Cache store must have an empty() method, saw type['
                . (!is_object($this->cacheStore) ? gettype($this->cacheStore) : get_class($this->cacheStore)) . '].'
            );
        }
        // Cache should live forever.
        // And setter/getter arg ttl should be ignored (we don't pass it anyway).
        // In effect: time-to-live should be ignored complete
        $this->cacheStore->setTtlIgnore(true);
        $this->cacheStore->setTtlDefault(ManagableCacheInterface::TTL_NONE);

        $paths = array_keys($this->paths);
        foreach ($paths as $path_name) {
            $opt_name = 'path' . ucfirst($path_name);
            if (!empty($options[$opt_name])) {
                if (!is_string($options[$opt_name])) {
                    throw new \TypeError('Arg options[' . $opt_name . '] type['
                        . (!is_object($options[$opt_name]) ? gettype($options[$opt_name]) :
                            get_class($options[$opt_name])) . '] is not string.');
                }
                $this->paths{$path_name} = $options[$opt_name];
            } else {
                $this->paths{$path_name} = static::PATH_DEFAULTS[$path_name];
            }
        }

        // Secure dependency.
        $this->utils = Utils::getInstance();

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
     *      Using source sections, an .ini file doesn't declare a [section]
     *      before flat vars.
     * @throws \Throwable
     *      Propagated.
     */
    public function refresh() : bool
    {
        // Clear cache first, as promised.
        $this->cacheStore->clear();

        // Load all variables from .ini file sources.
        $collection = [];
        foreach ($this->paths as $path_name => $path) {
            // Convert path to absolute if required, and check that it exists.
            $absolute_path = $this->utils->resolvePath($path);
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
            $files = (new PathFileList($absolute_path, ['ini']))->getArrayCopy();
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
                                    '/\n;[^\n]+\n/m',
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
                                $settings_in_path += $this->utils->parseIniString($ini, true, true);
                            }
                        }
                    } else {
                        $settings_in_path += $this->utils->parseIniFile($path_file, false, true);
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

        // Pass to cache.
        if ($collection) {
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
