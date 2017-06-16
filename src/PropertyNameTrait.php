<?php
/**
 * SimpleComplex PHP Config
 * @link      https://github.com/simplecomplex/php-config
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Config;

/**
 * Expose instance property 'name' as readonly.
 *
 * @package SimpleComplex\Config
 */
trait PropertyNameTrait
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @param mixed $name
     *
     * @return string
     *
     * @throws \OutOfBoundsException
     */
    public function __get($name)
    {
        if ($name == 'name') {
            return $this->name;
        }
        throw new \OutOfBoundsException(get_class($this) . ' instance has no property[' . $name . '].');
    }

    /**
     * @param mixed $name
     * @param mixed $value
     *
     * @throws \OutOfBoundsException
     */
    public function __set($name, $value)
    {
        throw new \OutOfBoundsException(get_class($this) . ' instance has no property[' . $name . '].');
    }
}
