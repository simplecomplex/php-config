<?php
/**
 * SimpleComplex PHP Config
 * @link      https://github.com/simplecomplex/php-config
 * @copyright Copyright (c) 2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-utils/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Config;

use SimpleComplex\Utils\Utils;
use SimpleComplex\Config\Exception\ConfigurationException;

/**
 * Helper for configuration classes using .ini files as source,
 * and PSR-16 cache as store.
 *
 * @package SimpleComplex\Config
 */
class AbstractIniConfig
{
    /**
     * Legal non-alphanumeric characters of a key.
     *
     * The only non-alphanumeric characters allowed in .ini file sections
     * and variable names are hyphen, dot and underscore.
     * And spaces; but they're prone to cause havoc.
     *
     * For compatibility with caching, this validation must also meet
     * PSR-16 Simple Cache requirements.
     *
     * PSR-16 key requirements:
     * - at least: a-zA-Z\d_.
     * - not: {}()/\@:
     * - length: >=2 <=64
     *
     * Do not override; not healthy.
     *
     * @var string[]
     */
    const KEY_VALID_NON_ALPHANUM = [
        '-',
        '.',
        '_'
    ];

    /**
     * @var int[]
     */
    const KEY_VALID_LENGTH = [
        'min' => 2,
        'max' => 64,
    ];

    /**
     * @param string $key
     *
     * @return bool
     */
    public function keyValidate(string $key) : bool
    {
        $le = strlen($key);
        if ($le < static::KEY_VALID_LENGTH['min'] || $le > static::KEY_VALID_LENGTH['max']) {
            return false;
        }
        // Faster than a regular expression.
        return !!ctype_alnum('A' . str_replace(static::KEY_VALID_NON_ALPHANUM, '', $key));
    }

    /**
     * Whether to use [section]s of .ini file, or ignore them.
     *
     * @var bool
     */
    protected $useSourceSections;

    /**
     * Resolves base and override paths, and parses all their .ini files.
     *
     * @param string $pathName
     * @param array $options
     * @param string $pathDefault
     *
     * @return array
     *
     * @throws \TypeError
     *      Wrong type of arg options bucket.
     * @throws ConfigurationException
     *      If the path doesn't exist or isn't directory.
     *      if keyMode is domainSectioned and an .ini file doesn't declare
     *      a [section] before flat vars.
     */
    protected function findNParseIniFiles(string $pathName, array $options, string $pathDefault) : array
    {
        $utils = Utils::getInstance();

        // @todo: must support finding in subfolders.

        if (!empty($options[$pathName])) {
            if (!is_string($options[$pathName])) {
                throw new \TypeError('Arg options[' . $pathName . '] type['
                    . (!is_object($options[$pathName]) ? gettype($options[$pathName]) :
                        get_class($options[$pathName])) . '] is not string.');
            }
            $path = $utils->resolvePath($options[$pathName]);
        } else {
            $path = $utils->resolvePath($pathDefault);
        }
        if (!file_exists($path) || !is_dir($path)) {
            throw new ConfigurationException(
                $pathName . ' for configuration .ini files '
                . (!file_exists($this->{$path}) ? 'does not exist' : 'is not a directory') . ', path[' . $path . '].'
            );
        }

        $collection = [];
        $dir_iterator = new \DirectoryIterator($path);
        foreach ($dir_iterator as $item) {
            if (!$item->isDot() && $item->getExtension() == 'ini') {
                if ($this->useSourceSections) {
                    // Check that the whole configuration begins with a [section].
                    $ini = file_get_contents($path . '/' . $item->getFilename());
                    // Remove comments and leading empty lines.
                    $ini = ltrim(
                        preg_replace(
                            '/\n;[^\n]+\n/m',
                            "\n",
                            "\n" . str_replace("\r", '', $ini)
                        )
                    );
                    if (!trim($ini)) {
                        continue;
                    }
                    if (!preg_match('/^\[/', $ini)) {
                        throw new ConfigurationException(
                            'Using source sections, an .ini file must declare a [section] before flat vars, file['
                            . $path . '/' . $item->getFilename() . '].'
                        );
                    }
                    // Union; two files within same dir shouldn't declare the
                    // the same vars.
                    // But if they do, the latter will rule.
                    $collection += $utils->parseIniString($ini, true, true);
                } else {
                    $collection += $utils->parseIniFile($path . '/' . $item->getFilename(), false, true);
                }
            }
        }
        return $collection;
    }
}
