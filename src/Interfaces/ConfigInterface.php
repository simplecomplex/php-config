<?php
/**
 * SimpleComplex PHP Config
 * @link      https://github.com/simplecomplex/php-config
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-config/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Config\Interfaces;

/**
 * Like PSR-16 Simple Cache interface, except:
 * - no time-to-live; setters have no ttl argument
 * - type declared key parameters
 * - requires no methods clear() and deleteMultiple()
 *
 * To support cachability, keys must conform with PSR-16 key requirements:
 * - at least: a-zA-Z\d_.
 * - not: {}()/\@:
 * - length: >=2 <=64
 *
 * Implementations must expose instance property 'name'. The property is allowed
 * to be virtual; see SectionedWrapper::__get().
 *
 * @see \Psr\SimpleCache\CacheInterface
 * @see SectionedConfigInterface
 * @see SectionedWrapper::__get()
 *
 * @property-read string $name
 *
 * @package SimpleComplex\Config
 */
interface ConfigInterface
{
    /**
     * Fetches an item from the configuration store.
     *
     * @param string $key
     * @param mixed|null $default
     *
     * @return mixed|null
     *
     * @throws \InvalidArgumentException
     *   MUST be thrown if arg key string is not a legal value.
     */
    public function get(string $key, $default = null);

    /**
     * Saves/overwrites an item in the configuration store.
     *
     * It is allowed that this method does nothing.
     *
     * @param string $key
     * @param mixed $value
     *      Must be serializable.
     *
     * @return bool
     *
     * @throws \InvalidArgumentException
     *   MUST be thrown if arg key string is not a legal value.
     */
    public function set(string $key, $value) : bool;

    /**
     * Delete an item from the configuration store.
     *
     * It is allowed that this method does nothing.
     *
     * @param string $key
     *
     * @return bool
     *
     * @throws \InvalidArgumentException
     *   MUST be thrown if arg key string is not a legal value.
     */
    public function delete(string $key) : bool;

    /**
     * Fetches multiple config items by keys.
     *
     * @param array|object $keys
     * @param mixed|null $default
     *
     * @return array|object
     *
     * @throws \InvalidArgumentException
     *   MUST be thrown if arg keys isn't array|object,
     *   or if any of arg keys are not a legal value.
     */
    public function getMultiple($keys, $default = null);

    /**
     * Sets multiple config items by keys.
     *
     * It is allowed that this method does nothing.
     *
     * @param array|object $values
     *
     * @return bool
     *
     * @throws \InvalidArgumentException
     *   MUST be thrown if arg values isn't array|object,
     *   or if any of arg values are not a legal value.
     */
    public function setMultiple($values) : bool;

    /**
     * Determines whether an item is present in the configuration store.
     *
     * @param string $key
     *
     * @return bool
     *
     * @throws \InvalidArgumentException
     *   MUST be thrown if arg key string is not a legal value.
     */
    public function has(string $key) : bool;


    // Expose read-only instance property 'name'.

    /**
     * @param mixed $name
     *
     * @return string
     */
    public function __get($name);

    /**
     * @param mixed $name
     * @param mixed $value
     *
     * @throws \RuntimeException
     *      When arg name is 'name'.
     */
    public function __set($name, $value);
}
