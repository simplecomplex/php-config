<?php
/**
 * SimpleComplex PHP Config
 * @link      https://github.com/simplecomplex/php-config
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Config;

use \SimpleComplex\Config\ConfigKey;
use \SimpleComplex\Config\Exception\InvalidArgumentException;

/**
 * Wraps a simple config instance as a sectioned config instance.
 *
 * Enables classes using configuration to support SectionedConfigInterface
 * _and_ ConfigInterface transparently.
 *
 * @property-read string $name
 *      Name of internal ConfigInterface instance.
 *
 * @package SimpleComplex\Config
 */
class SectionedWrapper implements SectionedConfigInterface
{
    /**
     * Reference to first object instantiated via the getInstance() method,
     * no matter which parent/child class the method was/is called on.
     *
     * @var SectionedWrapper
     */
    protected static $instance;

    /**
     * First object instantiated via this method, disregarding class called on.
     *
     * @param mixed ...$constructorParams
     *
     * @return SectionedWrapper
     *      static, really, but IDE might not resolve that.
     */
    public static function getInstance(...$constructorParams)
    {
        if (!static::$instance) {
            static::$instance = new static(...$constructorParams);
        }
        return static::$instance;
    }


    // SectionedConfigInterface.------------------------------------------------

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
            return $this->config->name;
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

    /**
     * @inheritdoc
     */
    public function get(string $section, string $key, $default = null)
    {
        return $this->config->get($section . $this->sectionKeyDelimiter . $key, $default);
    }

    /**
     * @inheritdoc
     */
    public function set(string $section, string $key, $value) : bool
    {
        return $this->config->set($section . $this->sectionKeyDelimiter . $key, $value);
    }

    /**
     * @inheritdoc
     */
    public function delete(string $section, string $key) : bool
    {
        return $this->config->delete($section . $this->sectionKeyDelimiter . $key);
    }

    /**
     * @inheritdoc
     */
    public function getMultiple(string $section, /*iterable*/ $keys, $default = null)
    {
        $sctnd = [];
        foreach ($keys as $key) {
            $sctnd[] = $section . $this->sectionKeyDelimiter . $key;
        }
        return $this->config->getMultiple($sctnd, $default);
    }

    /**
     * @inheritdoc
     */
    public function setMultiple(string $section, /*iterable*/ $values) : bool
    {
        $sctnd = [];
        foreach ($values as $key => $value) {
            $sctnd[$section . $this->sectionKeyDelimiter . $key] = $value;
        }
        return $this->config->setMultiple($sctnd);
    }

    /**
     * @inheritdoc
     */
    public function has(string $section, string $key) : bool
    {
        return $this->config->has($section . $this->sectionKeyDelimiter . $key);
    }

    /**
     * Does nothing.
     *
     * @inheritdoc
     */
    public function remember(string $section) : null
    {
        return null;
    }

    /**
     * Does nothing.
     *
     * @inheritdoc
     */
    public function forget(string $section) /*: void*/
    {
    }


    // Business.----------------------------------------------------------------

    /**
     * Default delimiter between args section and key.
     *
     * PSR-16 Simple Cache forbids:
     * {}()/\@:
     *
     * @var string
     */
    const SECTION_KEY_DELIMITER = '__';

    /**
     * Internal simple config instance.
     *
     * @var ConfigInterface
     */
    protected $config;

    /**
     * Delimiter between args section and key.
     *
     * PSR-16 Simple Cache forbids:
     * {}()/\@:
     *
     * @var string
     */
    protected $sectionKeyDelimiter;

    /**
     * SectionedWrapper constructor.
     *
     * @param ConfigInterface $config
     * @param string|null $sectionKeyDelimiter
     *      Null: class default SECTION_KEY_DELIMITER rules.
     *
     * @throws \TypeError
     *      Arg sectionKeyDelimiter not string|null.
     */
    public function __construct(ConfigInterface $config, /*?string*/ $sectionKeyDelimiter = null)
    {
        $this->config = $config;

        if ($sectionKeyDelimiter !== null) {
            if (!is_string($sectionKeyDelimiter)) {
                throw new \TypeError(
                    'Arg sectionKeyDelimiter type['
                    . (!is_object($sectionKeyDelimiter) ? gettype($sectionKeyDelimiter) :
                        get_class($sectionKeyDelimiter)
                    )
                    . '] is string or null.'
                );
            }
            if (!ConfigKey::validate($sectionKeyDelimiter)) {
                throw new InvalidArgumentException(
                    'Arg sectionKeyDelimiter is not valid, sectionKeyDelimiter[' . $sectionKeyDelimiter . '].'
                );
            }
            $this->sectionKeyDelimiter = $sectionKeyDelimiter;
        } else {
            $this->sectionKeyDelimiter = static::SECTION_KEY_DELIMITER;
        }
    }
}
