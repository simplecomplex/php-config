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
 * Wraps a simple config instance as a sectioned config instance.
 *
 * Enables classes using configuration to support SectionedConfigInterface
 * _and_ ConfigInterface transparently.
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
        return $this->config->get($section . static::SECTION_KEY_DELIMITER . $key, $default);
    }

    /**
     * @inheritdoc
     */
    public function set(string $section, string $key, $value) : bool
    {
        return $this->config->set($section . static::SECTION_KEY_DELIMITER . $key, $value);
    }

    /**
     * @inheritdoc
     */
    public function delete(string $section, string $key) : bool
    {
        return $this->config->delete($section . static::SECTION_KEY_DELIMITER . $key);
    }

    /**
     * @inheritdoc
     */
    public function getMultiple(string $section, /*iterable*/ $keys, $default = null)
    {
        $sctnd = [];
        foreach ($keys as $key) {
            $sctnd[] = $section . static::SECTION_KEY_DELIMITER . $key;
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
            $sctnd[$section . static::SECTION_KEY_DELIMITER . $key] = $value;
        }
        return $this->config->setMultiple($sctnd);
    }

    /**
     * @inheritdoc
     */
    public function has(string $section, string $key) : bool
    {
        return $this->config->has($section . static::SECTION_KEY_DELIMITER . $key);
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

    const SECTION_KEY_DELIMITER = '[.]';

    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * SectionedWrapper constructor.
     *
     * @param ConfigInterface $config
     */
    public function __construct(ConfigInterface $config)
    {
        $this->config = $config;
    }
}
