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
 * Wrapped environment variable configuration object.
 *
 * @see EnvConfig
 *
 * @package SimpleComplex\Config
 */
class EnvSectionedConfig extends SectionedWrapper
{
    /**
     * Reference to first object instantiated via the getInstance() method,
     * no matter which parent/child class the method was/is called on.
     *
     * @var EnvSectionedConfig
     */
    protected static $instance;

    /**
     * First object instantiated via this method, disregarding class called on.
     *
     * @param mixed ...$constructorParams
     *
     * @return EnvSectionedConfig
     *      static, really, but IDE might not resolve that.
     */
    public static function getInstance(...$constructorParams)
    {
        // Unsure about null ternary ?? for class and instance vars.
        if (!static::$instance) {
            static::$instance = new static(...$constructorParams);
        }
        return static::$instance;
    }


    // SectionedWrapper.--------------------------------------------------------

    /**
     * @param string $name
     */
    public function __construct($name = 'environment')
    {
        parent::__construct(EnvConfig::getInstance($name), '__');
    }
}
