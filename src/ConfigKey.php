<?php
/**
 * SimpleComplex PHP Config
 * @link      https://github.com/simplecomplex/php-config
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-config/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Config;

use SimpleComplex\Cache\CacheKey;

/**
 * Validate config key.
 *
 * Stricter than cache key, to support .ini file format as configuration source.
 *
 * @code
 * use SimpleComplex\Config\ConfigKey;
 *
 * if (!ConfigKey::validate($key)) {
 *    throw new \InvalidArgumentException('Arg key is not valid, key[' . $key . '].');
 * }
 * @endcode
 *
 * @see CacheKey
 *
 * @package SimpleComplex\Config
 */
class ConfigKey extends CacheKey
{
    /**
     * @see CacheKey::VALID_LENGTH_MIN
     * @see CacheKey::VALID_LENGTH_MAX
     */

    /**
     * Legal non-alphanumeric characters of a config key.
     *
     * Stricter than cache key, to support .ini file format as configuration
     * source.
     *
     * The only non-alphanumeric characters allowed in .ini file sections
     * and variable names are hyphen, dot and underscore.
     * And spaces; but they're prone to cause havoc.
     */
    const VALID_NON_ALPHANUM = [
        '-',
        '.',
        '_'
    ];

    /**
     * @see CacheKey::validate()
     */
}
