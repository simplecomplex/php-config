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
 * Exposes configuration items as children of sections, having composite keys
 * as 'section' + 'key'.
 *
 * Behind the scene, an implementation may arrange and handle items
 * as multi-dimensional objects/arrays.
 * However it is not recommended to expose such internal objects/arrays
 * directly - as in returning a handle to a 'section'.
 *
 * The rationale behind sectioning is three-fold:
 * i.   Design flexibility: a class using configuration can allow extending
 *      classes to override which config section to be used.
 * ii.  Convenience: shorter variable names.
 * iii. Performance: less i/o when retrieving multiple items of same 'family'.
 *
 * For cachability, sections and keys must conform with PSR-16 key requirements:
 * - at least: a-zA-Z\d_.
 * - not: {}()/\@:
 * - length: >=2 <=64
 *
 * Implementations must expose instance property 'name'. The property is allowed
 * to be virtual; see SectionedWrapper::__get().
 *
 * @see ConfigInterface
 * @see SectionedWrapper::__get()
 *
 * @property-read string $name
 *
 * @package SimpleComplex\Config
 */
interface SectionedConfigInterface
{
    /**
     * Fetches a value from the configuration store.
     *
     * An implementation may support wildcard * for the get() method's key
     * argument, and thus return the whole section as an iterable;
     * but only as copy, not as reference.
     *
     * @param string $section
     * @param string $key
     * @param mixed|null $default
     *
     * @return mixed|null
     *
     * @throws \InvalidArgumentException
     *   MUST be thrown if arg key string is not a legal value.
     */
    public function get(string $section, string $key, $default = null);

    /**
     * Persists data in the configuration store, uniquely referenced by section
     * plus key.
     *
     * @param string $section
     * @param string $key
     * @param mixed $value
     *      Must be serializable.
     *
     * @return bool
     *
     * @throws \InvalidArgumentException
     *   MUST be thrown if arg key string is not a legal value.
     */
    public function set(string $section, string $key, $value) : bool;

    /**
     * Delete an item from the configuration store.
     *
     * @param string $section
     * @param string $key
     *
     * @return bool
     *
     * @throws \InvalidArgumentException
     *   MUST be thrown if arg key string is not a legal value.
     */
    public function delete(string $section, string $key) : bool;

    /**
     * Obtains multiple config items by section and keys.
     *
     * @param string $section
     * @param iterable $keys
     * @param mixed|null $default
     *
     * @return iterable
     *
     * @throws \InvalidArgumentException
     *   MUST be thrown if arg keys is neither an array nor a Traversable,
     *   or if any of arg keys are not a legal value.
     */
    public function getMultiple(string $section, /*iterable*/ $keys, $default = null) /*: iterable*/;

    /**
     * Set multiple config items by section and keys.
     *
     * @param string $section
     * @param iterable $values
     *
     * @return bool
     *
     * @throws \InvalidArgumentException
     *   MUST be thrown if arg values is neither an array nor a Traversable,
     *   or if any of arg values are not a legal value.
     */
    public function setMultiple(string $section, /*iterable*/ $values) : bool;

    /**
     * Determines whether an item is present in the configuration store.
     *
     * An implementation may support wildcard * for the has() method's key
     * argument, and thus check if the section (as an iterable) exists.
     *
     * @param string $section
     * @param string $key
     *
     * @return bool
     *
     * @throws \InvalidArgumentException
     *   MUST be thrown if arg key string is not a legal value.
     */
    public function has(string $section, string $key) : bool;

    /**
     * Load section into memory, to make subsequent getter calls read
     * from memory instead of physical store.
     *
     * A subsequent call to a setting or deleting method using arg section
     * _must_ (for integrity reasons) immediately clear the section from memory.
     *
     * An implementation which internally can't/won't arrange items
     * multi-dimensionally (and thus cannot load a section into memory)
     * must return null.
     *
     * @param string $section
     *
     * @return bool|null
     *      False: section doesn't exist.
     *      Null: Not applicable.
     */
    public function remember(string $section) /*: ?bool*/;

    /**
     * Flush section from memory, to relieve memory usage; and make subsequent
     * getter calls read from physical store.
     *
     * Implementations which cannot do this, must ignore call.
     *
     * @param string $section
     *
     * @return void
     */
    public function forget(string $section) /*: void*/;


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
