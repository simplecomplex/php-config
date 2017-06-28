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
 * @internal
 *
 * @package SimpleComplex\Locale
 */
class LocaleText extends IniSectionedFlatConfig
{

    /**
     * Paths to where localisation .ini-files reside.
     *
     * 'override' defaults to empty; as in 'ignore'.
     */
    const PATH_DEFAULTS = [
        'base' => 'services/locale/text',
        'override' => '',
    ];

    /**
     * LocaleText constructor.
     *
     * @param string $language
     *      Lisp-cased; 'en' or 'en-us'.
     * @param array $options {
     *      @var array $paths
     * }
     */
    public function __construct(string $language, array $options = [])
    {
        if (!$language) {
            throw new \InvalidArgumentException('Arg language cannot be empty.');
        }

        // @todo: there's a conflict with IniConfigBase paths (see ucfirst).
        if (!empty($options['paths'])) {

        }

        parent::__construct('locale-text_' . $language, $options);
    }
}
