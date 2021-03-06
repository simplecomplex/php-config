<?php
/**
 * SimpleComplex PHP Config
 * @link      https://github.com/simplecomplex/php-config
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-config/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Config;

use SimpleComplex\Utils\Utils;
use SimpleComplex\Config\Interfaces\ConfigInterface;
use SimpleComplex\Config\Exception\InvalidArgumentException;
use SimpleComplex\Config\Exception\OutOfBoundsException;
use SimpleComplex\Config\Exception\RuntimeException;

/**
 * Configuration using environment variables as source, and no caching.
 *
 * Use as singleton - there's only a single set of environment variables.
 *
 * @property-read string $name
 *
 * @package SimpleComplex\Config
 */
class EnvConfig implements ConfigInterface
{
    /**
     * Reference to first object instantiated via the getInstance() method,
     * no matter which parent/child class the method was/is called on.
     *
     * @var EnvConfig
     */
    protected static $instance;

    /**
     * Makes sense because singleton'ish.
     *
     * First object instantiated via this method, disregarding class called on.
     *
     * @param mixed ...$constructorParams
     *
     * @return EnvConfig
     *      static, really, but IDE might not resolve that.
     */
    public static function getInstance(...$constructorParams)
    {
        return static::$instance ?? (static::$instance = new static(...$constructorParams));
    }


    // ConfigInterface.----------------------------------------------------------

    /**
     * Fetches an environment variable.
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed|null
     *      Environment vars are always string.
     *      The default may be of any type.
     *
     * @throws InvalidArgumentException
     *      Propagated.
     */
    public function get(string $key, $default = null)
    {
        $k = $this->keyConvert($key);
        $v = getenv($k);
        return $v !== false ? $v : $default;
    }

    /**
     * Does nothing at all; setting/overwriting an environment var could have
     * security implications and/or result in peculiar errors.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return bool
     *      Always true.
     */
    public function set(string $key, $value) : bool
    {
        return true;
    }

    /**
     * Does nothing at all; setting/overwriting an environment var could have
     * security implications and/or result in peculiar errors.
     *
     * @param mixed $key
     *
     * @return bool
     *      Always true.
     */
    public function delete(string $key) : bool
    {
        return true;
    }

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param array|object $keys
     * @param mixed $default
     *
     * @return array
     *
     * @throws \TypeError
     *      Arg keys no array|object.
     * @throws InvalidArgumentException
     *      Propagated.
     */
    public function getMultiple($keys, $default = null) : array
    {
        if (!is_array($keys) && !is_object($keys)) {
            throw new \TypeError('Arg keys type[' . Utils::getType($keys) . '] is not array|object.');
        }
        $values = [];
        foreach ($keys as $k) {
            $values[$k] = $this->get($k, $default);
        }
        return $values;
    }

    /**
     * Does nothing at all.
     *
     * @param array|object $values
     *
     * @return bool
     *      Always true.
     */
    public function setMultiple($values) : bool
    {
        return true;
    }

    /**
     * Check if an environment var is set.
     *
     * @param string $key
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     *      Propagated.
     */
    public function has(string $key) : bool
    {
        $k = $this->keyConvert($key);
        return getenv($k) !== false;
    }


    // Expose read-only instance property 'name'.

    /**
     * @var string
     */
    protected $name;

    /**
     * @param mixed $name
     *
     * @return string
     *
     * @throws OutOfBoundsException
     */
    public function __get($name)
    {
        if ($name == 'name') {
            return $this->name;
        }
        throw new OutOfBoundsException(get_class($this) . ' instance has no property[' . $name . '].');
    }

    /**
     * @param mixed $name
     * @param mixed $value
     *
     * @throws OutOfBoundsException
     * @throws RuntimeException
     */
    public function __set($name, $value)
    {
        if ('' . $name == 'name') {
            throw new RuntimeException(get_class($this) . ' instance property[' . $name . '] is read-only.');
        }
        throw new OutOfBoundsException(get_class($this) . ' instance has no property[' . $name . '].');
    }


    // Custom/business.---------------------------------------------------------

    /**
     * @param string $name
     *
     * @throws InvalidArgumentException
     *      Invalid arg name.
     */
    public function __construct(string $name = 'environment')
    {
        if (!ConfigKey::validate($name)) {
            throw new InvalidArgumentException('Arg name is not valid, name[' . $name . '].');
        }
        $this->name = $name;
    }

    /**
     * Replaces all legal non-alphanumeric chars with underscore.
     *
     * @param string $key
     *
     * @return string
     *
     * @throws InvalidArgumentException
     */
    public function keyConvert(string $key) : string
    {
        $key = str_replace(ConfigKey::VALID_NON_ALPHANUM, '_', $key);
        if (!ConfigKey::validate($key)) {
            throw new InvalidArgumentException('Arg key contains invalid character(s).');
        }
        return $key;
    }
}
