<?php
/**
 * SimpleComplex PHP Config
 * @link      https://github.com/simplecomplex/php-config
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Config;

use SimpleComplex\Config\Exception\InvalidArgumentException;

/**
 * Configuration using environment variables as source, and no caching.
 *
 * @package SimpleComplex\Config
 */
class EnvVarConfig implements ConfigInterface
{
    /**
     * Reference to first object instantiated via the getInstance() method,
     * no matter which parent/child class the method was/is called on.
     *
     * @var EnvVarConfig
     */
    protected static $instance;

    /**
     * First object instantiated via this method, disregarding class called on.
     *
     * @param mixed ...$constructorParams
     *
     * @return EnvVarConfig
     *      static, really, but IDE might not resolve that.
     */
    public static function getInstance(...$constructorParams)
    {
        if (!static::$instance) {
            static::$instance = new static(...$constructorParams);
        }
        return static::$instance;
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
     * @param iterable $keys
     * @param mixed $default
     *
     * @return array
     *
     * @throws \TypeError
     *      Arg keys no iterable.
     * @throws InvalidArgumentException
     *      Propagated.
     */
    public function getMultiple(/*iterable*/ $keys, $default = null) : array
    {
        if (!is_array($keys) && !is_a($keys, \Traversable::class)) {
            throw new \TypeError(
                'Arg keys type[' . (!is_object($keys) ? gettype($keys) : get_class($keys)) . '] is not iterable.'
            );
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
     * @param iterable $values
     *
     * @return bool
     *      Always true.
     */
    public function setMultiple(/*iterable*/ $values) : bool
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


    // Custom/business.---------------------------------------------------------

    /**
     * Legal non-alphanumeric characters of a key.
     *
     * All non-alphanumerics get converted to underscore, so using any but
     * underscore may be a bad idea.
     */
    const KEY_VALID_NON_ALPHANUM = [
        '-',
        '.',
        '[',
        ']',
        '_'
    ];

    /**
     * @var int[]
     */
    const KEY_VALID_LENGTH = [
        'min' => 2,
        'max' => 64,
    ];

    /**
     * Checks that key is string, and that length and content is legal.
     *
     * @param string $key
     *
     * @return bool
     */
    public function keyValidate(string $key) : bool
    {
        $le = strlen($key);
        if ($le < static::KEY_VALID_LENGTH['min'] || $le > static::KEY_VALID_LENGTH['max']) {
            return false;
        }
        // Faster than a regular expression.
        return !!ctype_alnum('A' . str_replace(static::KEY_VALID_NON_ALPHANUM, '', $key));
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
        $key = str_replace(static::KEY_VALID_NON_ALPHANUM, '_', $key);
        if (!$this->keyValidate($key)) {
            throw new InvalidArgumentException('Arg key contains invalid character(s).');
        }
        return $key;
    }
}
