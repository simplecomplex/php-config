<?php
/**
 * SimpleComplex PHP Config
 * @link      https://github.com/simplecomplex/php-config
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-config/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Config;

/**
 * Convenience class.
 *
 * Consider sub-classing, to make it easier shifting to another
 * SectionedConfigInterface implementation.
 * Then to shift, simply extend another class than this.
 *
 * @dependency-injection-container config
 *      Suggested ID of the Config 'global' instance.
 *
 * @package SimpleComplex\Config
 */
class Config extends IniSectionedConfig
{
    /**
     * @var IniSectionedConfig[]
     */
    protected static $instances = [];

    /**
     * First object instantiated via this method and with that arg name,
     * disregarding class called on.
     *
     * @deprecated Use a dependency injection container instead.
     * @see \SimpleComplex\Utils\Dependency
     *
     * @param string $name
     *      Default: global
     *
     * @return IniSectionedConfig
     *      static, really, but IDE might not resolve that.
     */
    public static function getInstance($name = 'global')
    {
        return static::$instances[$name] ?? (static::$instances[$name] = new static($name));
    }
}
