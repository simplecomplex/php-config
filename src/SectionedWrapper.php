<?php
/**
 * SimpleComplex PHP Config
 * @link      https://github.com/simplecomplex/php-config
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-config/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Config;

use SimpleComplex\Config\Exception\InvalidArgumentException;
use SimpleComplex\Config\Exception\OutOfBoundsException;
use SimpleComplex\Config\Exception\RuntimeException;

/**
 * Wraps a simple config instance as a sectioned config instance.
 *
 * Enables classes using configuration to support SectionedConfigInterface
 * _and_ ConfigInterface transparently.
 * If a using class instance receives a ConfigInterface it can wrap that
 * as a SectionedConfigInterface.
 *
 * Weird but effective pattern.
 *
 * @see EnvSectionedConfig
 *
 * @property-read string $name
 *      Name of internal ConfigInterface instance.
 *
 * @package SimpleComplex\Config
 */
class SectionedWrapper implements SectionedConfigInterface
{
    // SectionedConfigInterface.------------------------------------------------

    /**
     * Arg section or key is allowed to be empty.
     *
     * Does _not_ support wildcard * for arg key.
     *
     * @see SectionedConfigInterface::get()
     *
     * @inheritdoc
     */
    public function get(string $section, string $key, $default = null)
    {
        return $this->config->get($this->concat($section, $key), $default);
    }

    /**
     * Arg section or key is allowed to be empty.
     *
     * @see SectionedConfigInterface::set()
     *
     * @inheritdoc
     */
    public function set(string $section, string $key, $value) : bool
    {
        return $this->config->set($this->concat($section, $key), $value);
    }

    /**
     * Arg section or key is allowed to be empty.
     *
     * @see SectionedConfigInterface::delete()
     *
     * @inheritdoc
     */
    public function delete(string $section, string $key) : bool
    {
        return $this->config->delete($this->concat($section, $key));
    }

    /**
     * Arg section or key is allowed to be empty.
     *
     * @see SectionedConfigInterface::getMultiple()
     *
     * @inheritdoc
     */
    public function getMultiple(string $section, $keys, $default = null)
    {
        $sctnd = [];
        foreach ($keys as $key) {
            $sctnd[] = $this->concat($section, $key);
        }
        return $this->config->getMultiple($sctnd, $default);
    }

    /**
     * Arg section or key is allowed to be empty.
     *
     * @see SectionedConfigInterface::setMultiple()
     *
     * @inheritdoc
     */
    public function setMultiple(string $section, $values) : bool
    {
        $sctnd = [];
        foreach ($values as $key => $value) {
            $sctnd[$this->concat($section, $key)] = $value;
        }
        return $this->config->setMultiple($sctnd);
    }

    /**
     * Arg section or key is allowed to be empty.
     *
     * Does _not_ support wildcard * for arg key.
     *
     * @see SectionedConfigInterface::has()
     *
     * @inheritdoc
     */
    public function has(string $section, string $key) : bool
    {
        return $this->config->has($this->concat($section, $key));
    }

    /**
     * Does nothing.
     *
     * @see SectionedConfigInterface::remember()
     *
     * @inheritdoc
     *
     * @return null
     */
    public function remember(string $section)
    {
        return null;
    }

    /**
     * Does nothing.
     *
     * @see SectionedConfigInterface::forget()
     *
     * @inheritdoc
     */
    public function forget(string $section) /*: void*/
    {
    }


    // Expose read-only instance property 'name'.

    /**
     * Exposes internal ConfigInterface's name property as this instance'
     * (virtual) name property.
     *
     * @param mixed $name
     *
     * @return string
     *
     * @throws OutOfBoundsException
     */
    public function __get($name)
    {
        if ($name == 'name') {
            return $this->config->name;
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
     * Wraps a simple config instance as sectioned config instance.
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

    /**
     * @param string $section
     * @param string $key
     *
     * @return string
     */
    protected function concat(string $section, string $key)
    {
        if ($section === '') {
            return $key;
        }
        if ($key === '') {
            return $section;
        }
        return $section . $this->sectionKeyDelimiter . $key;
    }
}
