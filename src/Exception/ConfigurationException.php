<?php

namespace SimpleComplex\Config\Exception;

/**
 * Exception indicating general configuration error, not a 'config' exception.
 *
 * To differentiate exceptions thrown in-package from exceptions
 * thrown out-package.
 *
 * Please do not use - throw - in code of another package/library.
 *
 * @package SimpleComplex\Config
 */
class ConfigurationException extends \LogicException
{
}
