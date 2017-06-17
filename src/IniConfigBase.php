<?php
/**
 * SimpleComplex PHP Config
 * @link      https://github.com/simplecomplex/php-config
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Config;

use Psr\SimpleCache\CacheInterface;
use SimpleComplex\Utils\Explorable;
use SimpleComplex\Utils\Utils;
use SimpleComplex\Cache\CacheBroker;
use SimpleComplex\Cache\CheckEmptyCacheInterface;
use SimpleComplex\Config\Exception\InvalidArgumentException;
use SimpleComplex\Config\Exception\OutOfBoundsException;
use SimpleComplex\Config\Exception\ConfigurationException;
use SimpleComplex\Config\Exception\RuntimeException;

/**
 * Helper for configuration classes using .ini files as source,
 * and PSR-16 cache as store.
 *
 * @property-read string $name
 * @property-read bool $useSourceSections
 * @property-read string $pathBase
 * @property-read string $pathOverride
 * @property-read CacheInterface $cacheStore
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
     * Whether to use [section]s of .ini file, or ignore them.
     *
     * @var bool
     */
    protected $useSourceSections = false;

    /**
     * Config's cache store.
     *
     * @var \Psr\SimpleCache\CacheInterface
     */
    protected $cacheStore;


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
        switch ($name) {
            case 'pathBase':
            case 'pathOverride':
                return $this->paths[$name == 'pathBase' ? 'base' : 'override'];
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
    public function __set(string $name, $value) /*: void*/
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
     * @throws \LogicException
     *      CacheBroker returns a cache store which has no empty() method.
     * @throws ConfigurationException
     *      Propagated. If pathBase or pathOverride doesn't exist or isn't directory.
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
        if (
            !is_a($this->cacheStore, CheckEmptyCacheInterface::class)
            && !method_exists($this->cacheStore, 'empty')
        ) {
            throw new \LogicException(
                'Cache store must have an empty() method, saw type['
                . (!is_object($this->cacheStore) ? gettype($this->cacheStore) : get_class($this->cacheStore)) . '].'
            );
        }

        $paths = array_keys($this->paths);
        foreach ($paths as $path_name) {
            $opt_name = 'path' . ucfirst($path_name);
            if (!empty($options[$opt_name])) {
                if (!is_string($options[$opt_name])) {
                    throw new \TypeError('Arg options[' . $opt_name . '] type['
                        . (!is_object($options[$opt_name]) ? gettype($options[$opt_name]) :
                            get_class($options[$opt_name])) . '] is not string.');
                }
                $this->{$path_name} = $options[$opt_name];
            } else {
                $this->{$path_name} = static::PATH_DEFAULTS[$path_name];
            }
        }

        // Don't import from .ini-files if our cache store has items.
        if (!$this->cacheStore->empty()) {
            return;
        }

        // Resolve 'base' and 'override' .ini-file dirs, and parse their files.
        /*$collection = array_replace_recursive(
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
        }*/
    }

    /**
     * @param string $pathName
     *
     * @return array
     */
    protected function loadFromSource(string $pathName) : array
    {
        /*
        // http://php.net/manual/en/class.recursivefilteriterator.php

$path = '/www/00_simplecomplex/sites/php-psr.source/conf/ini';
$list = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($path, FilesystemIterator::UNIX_PATHS | FilesystemIterator::FOLLOW_SYMLINKS | FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
foreach ($list as $item) {
    $name = $item->getFilename();
    if ($name{0} === '.') {
        continue;
    }

    echo $item->getPath() . '/' . $name . "\n";
}
         */
    }

    /**
     * Resolves base and override paths, and parses all their .ini files.
     *
     * @param string $pathName
     * @param array $options
     * @param string $pathDefault
     *
     * @return array
     *
     * @throws \TypeError
     *      Wrong type of arg options bucket.
     * @throws ConfigurationException
     *      If the path doesn't exist or isn't directory.
     *      if keyMode is domainSectioned and an .ini file doesn't declare
     *      a [section] before flat vars.
     */
    protected function findNParseIniFiles(string $pathName, array $options, string $pathDefault) : array
    {
        $utils = Utils::getInstance();

        // @todo: must support finding in subfolders.

        if (!empty($options[$pathName])) {
            if (!is_string($options[$pathName])) {
                throw new \TypeError('Arg options[' . $pathName . '] type['
                    . (!is_object($options[$pathName]) ? gettype($options[$pathName]) :
                        get_class($options[$pathName])) . '] is not string.');
            }
            $path = $utils->resolvePath($options[$pathName]);
        } else {
            $path = $utils->resolvePath($pathDefault);
        }
        if (!file_exists($path) || !is_dir($path)) {
            throw new ConfigurationException(
                $pathName . ' for configuration .ini files '
                . (!file_exists($this->{$path}) ? 'does not exist' : 'is not a directory') . ', path[' . $path . '].'
            );
        }

        $collection = [];
        $dir_iterator = new \DirectoryIterator($path);
        foreach ($dir_iterator as $item) {
            if (!$item->isDot() && $item->getExtension() == 'ini') {
                if ($this->useSourceSections) {
                    // Check that the whole configuration begins with a [section].
                    $ini = file_get_contents($path . '/' . $item->getFilename());
                    // Remove comments and leading empty lines.
                    $ini = ltrim(
                        preg_replace(
                            '/\n;[^\n]+\n/m',
                            "\n",
                            "\n" . str_replace("\r", '', $ini)
                        )
                    );
                    if (!trim($ini)) {
                        continue;
                    }
                    if (!preg_match('/^\[/', $ini)) {
                        throw new ConfigurationException(
                            'Using source sections, an .ini file must declare a [section] before flat vars, file['
                            . $path . '/' . $item->getFilename() . '].'
                        );
                    }
                    // Union; two files within same dir shouldn't declare the
                    // the same vars.
                    // But if they do, the latter will rule.
                    $collection += $utils->parseIniString($ini, true, true);
                } else {
                    $collection += $utils->parseIniFile($path . '/' . $item->getFilename(), false, true);
                }
            }
        }
        return $collection;
    }
}
