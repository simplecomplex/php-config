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
 * Use as singleton - there's only a single set of environment variables.
 *
 * Weird but effective pattern. See SectionedWrapper for methods.
 *
 * @see SectionedWrapper
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
     * Makes sense because singleton'ish.
     *
     * First object instantiated via this method, disregarding class called on.
     *
     * SectionedWrapper cannot have getInstance() method (wouldn't make sense)
     * so ...$constructorParams parameter(s) are not needed for method signature
     * compatibility.
     *
     * @return EnvSectionedConfig
     *      static, really, but IDE might not resolve that.
     */
    public static function getInstance()
    {
        return static::$instance ?? (static::$instance = new static());
    }


    // SectionedWrapper.--------------------------------------------------------

    /**
     * Passes EnvConfig default instance to SectionedWrapper constructor.
     *
     * @see EnvConfig::getInstance()
     */
    public function __construct()
    {
        parent::__construct(EnvConfig::getInstance(), '__');
    }
}
