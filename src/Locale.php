<?php
/**
 * SimpleComplex PHP Locale
 * @link      https://github.com/simplecomplex/php-locale
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-locale/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Locale;

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
     * @var IniSectionedFlatConfig
     */
    protected $text;

    /**
     * @param string $language
     */
    public function __construct(string $language = 'en-us')
    {
        $this->text = new LocaleText($language);
    }
}
