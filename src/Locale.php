<?php
/**
 * SimpleComplex PHP Locale
 * @link      https://github.com/simplecomplex/php-locale
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-locale/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Locale;

use SimpleComplex\Config\SectionedConfigInterface;
use SimpleComplex\Config\IniSectionedFlatConfig;

/**
 * ???
 *
 * @property-read LocaleText $text
 *
 * @package SimpleComplex\Locale
 */
class Locale
{
    /**
     * Config vars, and their effective defaults:
     *  - (arr) text_paths
     *
     * See also ../config-ini/locale.ini
     *
     * @see Locale::CONFIG_SECTION
     *
     * @var SectionedConfigInterface
     */
    public $config;

    /**
     * @var IniSectionedFlatConfig
     */
    protected $text;

    /**
     * @param string $language
     * @param SectionedConfigInterface $config
     */
    public function __construct(string $language = 'en-us', SectionedConfigInterface $config)
    {
        $this->text = new LocaleText($language, $config->get(static::CONFIG_SECTION, 'text_paths', []));
    }

    /**
     * Config var default section.
     *
     * @var string
     */
    const CONFIG_SECTION = 'lib_simplecomplex_locale';
}
