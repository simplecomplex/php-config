<?php
/**
 * SimpleComplex PHP Config
 * @link      https://github.com/simplecomplex/php-config
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-config/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Config;

use SimpleComplex\Cache\CacheKeyLong;

/**
 * Validate long config key.
 *
 * Stricter than cache key, to support .ini file format as configuration source.
 *
 * @code
 * use SimpleComplex\Config\ConfigKeyLong;
 *
 * if (!ConfigKeyLong::validate($key)) {
 *    throw new \InvalidArgumentException('Arg key is not valid, key[' . $key . '].');
 * }
 * @endcode
 *
 * @see CacheKey
 *
 * @package SimpleComplex\Config
 */
class ConfigKeyLong extends CacheKeyLong
{
    /**
     * @see CacheKey::VALID_LENGTH_MIN
     * @see CacheKeyLong::VALID_LENGTH_MAX
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
