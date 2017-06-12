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
 * Like PSR-16 Simple Cache interface, except:
 * - no time-to-live; setters have no ttl argument
 * - type declared key parameters
 * - requires no methods clear() and deleteMultiple()
 * - requires a keyDomainDelimiter() method
 *
 * @see \Psr\SimpleCache\CacheInterface
 *
 * @package SimpleComplex\Config
 */
interface ConfigInterface
{
    /**
     * Fetches a value from the configuration store.
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
     * Persists data in the configuration store, uniquely referenced by a key.
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
     * @param string $key
     *
     * @return bool
     *
     * @throws \InvalidArgumentException
     *   MUST be thrown if arg key string is not a legal value.
     */
    public function delete(string $key) : bool;

    /**
     * Obtains multiple config items by their unique keys.
     *
     * @param iterable $keys
     * @param mixed|null $default
     *
     * @return iterable
     *
     * @throws \InvalidArgumentException
     *   MUST be thrown if arg keys is neither an array nor a Traversable,
     *   or if any of arg keys are not a legal value.
     */
    public function getMultiple(/*iterable*/ $keys, $default = null) /*: iterable*/;

    /**
     * Persists a set of key => value pairs in the configuration store.
     *
     * @param iterable $values
     *
     * @return bool
     *
     * @throws \InvalidArgumentException
     *   MUST be thrown if arg values is neither an array nor a Traversable,
     *   or if any of arg values are not a legal value.
     */
    public function setMultiple(/*iterable*/ $values) : bool;

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

    /**
     * For domain:key namespaced use.
     *
     * Delimiter between configuration domain and item key.
     *
     * @return string
     */
    public function keyDomainDelimiter() : string;
}
