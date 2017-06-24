<?php
/**
 * SimpleComplex PHP Config
 * @link      https://github.com/simplecomplex/php-config
 * @copyright Copyright (c) 2014-2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-config/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Config;

use SimpleComplex\Utils\CliCommandInterface;
use SimpleComplex\Utils\CliEnvironment;
use SimpleComplex\Utils\CliCommand;

/**
 * CLI only.
 *
 * Expose/execute config commands.
 *
 * @see cli_config()
 *
 * @see IniSectionedConfig::get()
 * @see IniSectionedConfig::set()
 * @see IniSectionedConfig::delete()
 * @see IniConfigBase::refresh()
 *
 * @code
 * # CLI
 * cd vendor/simplecomplex/config/src/cli
 * php cli.phpsh config -h
 * @endcode
 *
 * @package SimpleComplex\Config
 */
class CliConfig implements CliCommandInterface
{
    /**
     * @var string
     */
    const COMMAND_PROVIDER_ALIAS = 'config';

    /**
     * Registers Config CliCommands at CliEnvironment.
     *
     * @throws \LogicException
     *      If executed in non-CLI mode.
     */
    public function __construct()
    {
        if (!CliEnvironment::cli()) {
            throw new \LogicException('Cli mode only.');
        }

        $this->environment = CliEnvironment::getInstance();
        // Declare supported commands.
        $this->environment->registerCommands(
            new CliCommand(
                $this,
                static::COMMAND_PROVIDER_ALIAS . '-get',
                'Get a config item.',
                [
                    'store' => 'Config store name.',
                    'section' => 'Config section.',
                    'key' => 'Config item key. Skip if --all.',
                ],
                [
                    'all' => 'Get the whole section.',
                    'print' => 'Print to console, don\'t return value.',
                    'inspect' => 'Print Inspect\'ed value instead of JSON-encoded.',
                ],
                [
                    'a' => 'all',
                    'p' => 'print',
                    'i' => 'inspect',
                ]
            ),
            new CliCommand(
                $this,
                static::COMMAND_PROVIDER_ALIAS . '-set',
                'Set a config item.',
                [
                    'store' => 'Config store name.',
                    'section' => 'Config section.',
                    'key' => 'Config item key.',
                    'value' => 'Value to set, please enclose in single quotes'
                    . "\n" . 'when not non-negative int/float or boolean int.',
                ],
                [
                    'int' => 'Set as integer.',
                    'float' => 'Set as float.',
                    'bool' => 'Set as boolean, use 0|1|true|false for arg value.',
                    'json' => 'Arg value is JSON-encoded.',
                ],
                [
                    'i' => 'int',
                    'f' => 'float',
                    'b' => 'bool',
                    'j' => 'json',
                ]
            ),
            new CliCommand(
                $this,
                static::COMMAND_PROVIDER_ALIAS . '-delete',
                'Delete a config item.',
                [
                    'store' => 'Config store name.',
                    'section' => 'Config section.',
                    'key' => 'Config item key.',
                ],
                [],
                []
            ),
            new CliCommand(
                $this,
                static::COMMAND_PROVIDER_ALIAS . '-refresh',
                'Flushes the config store\'s cache store,'
                . ' and loads fresh configuration from all .ini files in the base and override paths.'
                . "\n" . 'NB: All items that have been set, overwritten or deleted since last refresh'
                . ' will be gone or restored to .ini-files\' original state.',
                [
                    'store' => 'Config store name.',
                ],
                [],
                []
            )
        );
    }

    /**
     * @var string
     */
    const CLASS_CONFIG = Config::class;

    /**
     * @var CliCommand
     */
    protected $command;

    /**
     * @var CliEnvironment
     */
    protected $environment;

    /**
     * To use class extending Inspect, call [ExtendedInspect]::getInstance()
     * before instantiating this class.
     *
     * @return \SimpleComplex\Inspect\Inspect|null
     */
    protected function getInspectInstance()
    {
        $class = '\\SimpleComplex\\Inspect\\Inspect';
        if (class_exists($class)) {
            return new $class();
        }
        return null;
    }

    /**
     * @return mixed
     *      Exits if option 'print'.
     */
    protected function cmdGet()
    {
        // Validate input. ---------------------------------------------
        $store = '';
        if (empty($this->command->arguments['store'])) {
            $this->command->inputErrors[] = !isset($this->command->arguments['store']) ? 'Missing \'store\' argument.' :
                'Empty \'store\' argument.';
        } else {
            $store = $this->command->arguments['store'];
            if (!ConfigKey::validate($store)) {
                $this->command->inputErrors[] = 'Invalid \'store\' argument.';
            }
        }
        $section = '';
        if (empty($this->command->arguments['section'])) {
            $this->command->inputErrors[] = !isset($this->command->arguments['section']) ?
                'Missing \'section\' argument.' : 'Empty \'section\' argument.';
        } else {
            $section = $this->command->arguments['section'];
            if (!ConfigKey::validate($section)) {
                $this->command->inputErrors[] = 'Invalid \'section\' argument.';
            }
        }
        $key = '';
        $all_keys = !empty($this->command->options['all']);
        if (empty($this->command->arguments['key'])) {
            if (!$all_keys) {
                $this->command->inputErrors[] = !isset($this->command->arguments['key']) ?
                    'Missing \'key\' argument, and option \'all\' not set.' :
                    'Empty \'key\' argument, and option \'all\' not set.';
            }
        } else {
            $key = $this->command->arguments['key'];
            if (!ConfigKey::validate($key)) {
                $this->command->inputErrors[] = 'Invalid \'key\' argument.';
            }
            if ($all_keys) {
                $this->command->inputErrors[] = 'Ambiguous input, saw argument \'key\' plus options \'all\'.';
            }
        }

        $print = !empty($this->command->options['print']);
        $inspect = !empty($this->command->options['inspect']);

        if ($this->command->inputErrors) {
            foreach ($this->command->inputErrors as $msg) {
                $this->environment->echoMessage(
                    $this->environment->format($msg, 'hangingIndent'),
                    'notice'
                );
            }
            // This command's help text.
            $this->environment->echoMessage("\n" . $this->command);
            exit;
        }
        // Display command and the arg values used.---------------------
        if ($print || $inspect) {
            $this->environment->echoMessage(
                $this->environment->format(
                    $this->environment->format($this->command->name, 'emphasize')
                    . "\n" . 'store: ' . $store
                    . "\n" . 'section: ' . $section
                    . "\n" . (!$all_keys ? ('key: ' . $key) : 'the whole section')
                    . (!$this->command->options ? '' : ("\n--" . join(' --', array_keys($this->command->options)))),
                    'hangingIndent'
                )
            );
        }
        // Check if the command is doable.------------------------------
        // Nothing to check here.
        $config_class = static::CLASS_CONFIG;
        /** @var IniSectionedConfig $config_store */
        $config_store = forward_static_call_array($config_class . '::getInstance', [$store]);
        // Do it.
        if ($all_keys) {
            if (!$config_store->has($section, '*')) {
                $this->environment->echoMessage(
                    'Config store[' . $store . '] section[' . $section . '] doesn\'t exist.',
                    'notice'
                );
                exit;
            } else {
                $value = $config_store->get($section, '*');
            }
        } else {
            if (!$config_store->has($section, $key)) {
                $this->environment->echoMessage(
                    'Config store[' . $store . '] section[' . $section . '] key[' . $key . '] doesn\'t exist.',
                    'notice'
                );
                exit;
            } else {
                $value = $config_store->get($section, $key);
            }
        }
        if (!$print && !$inspect) {
            return $value;
        }
        $this->environment->echoMessage('');
        if ($inspect && ($inspect = $this->getInspectInstance())) {
            $this->environment->echoMessage($inspect->inspect($value)->toString(true));
        } else {
            $this->environment->echoMessage(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
        exit;
    }

    /**
     * @return void
     *      Exits.
     */
    protected function cmdSet() /*: void*/
    {
        // Validate input. ---------------------------------------------
        $store = '';
        if (empty($this->command->arguments['store'])) {
            $this->command->inputErrors[] = !isset($this->command->arguments['store']) ? 'Missing \'store\' argument.' :
                'Empty \'store\' argument.';
        } else {
            $store = $this->command->arguments['store'];
            if (!ConfigKey::validate($store)) {
                $this->command->inputErrors[] = 'Invalid \'store\' argument.';
            }
        }
        $section = '';
        if (empty($this->command->arguments['section'])) {
            $this->command->inputErrors[] = !isset($this->command->arguments['section']) ?
                'Missing \'section\' argument.' : 'Empty \'section\' argument.';
        } else {
            $section = $this->command->arguments['section'];
            if (!ConfigKey::validate($section)) {
                $this->command->inputErrors[] = 'Invalid \'section\' argument.';
            }
        }
        $key = '';
        if (empty($this->command->arguments['key'])) {
            $this->command->inputErrors[] = !isset($this->command->arguments['key']) ?
                'Missing \'key\' argument.' : 'Empty \'key\' argument.';
        } else {
            $key = $this->command->arguments['key'];
            if (!ConfigKey::validate($key)) {
                $this->command->inputErrors[] = 'Invalid \'key\' argument.';
            }
        }

        $converted_value = $value = '';
        $int = !empty($this->command->options['int']);
        $float = !empty($this->command->options['float']);
        $bool = !empty($this->command->options['bool']);
        $json = !empty($this->command->options['json']);

        if (((int) $int + (int) $float + (int) $bool + (int) $json) > 1) {
            $this->command->inputErrors[] = 'Cannot use more than a single value type option.';
        } else {
            if (!isset($this->command->arguments['value'])) {
                $this->command->inputErrors[] = !isset($this->command->arguments['value']) ?
                    'Missing \'value\' argument.' : 'Empty \'value\' argument.';
            } else {
                $converted_value = $value = $this->command->arguments['value'];
                if ($int) {
                    if (!ctype_digit($value)) {
                        $this->command->inputErrors[] = 'Arg value[' . $value . '] is not an integer.';
                    } else {
                        $converted_value = (int) $value;
                    }
                } elseif ($float) {
                    if (!is_numeric($value)) {
                        $this->command->inputErrors[] = 'Arg value[' . $value . '] is not a float.';
                    } else {
                        $converted_value = (float) $value;
                    }
                } elseif ($bool) {
                    switch ($value) {
                        case '0':
                        case 'false':
                            $converted_value = false;
                            break;
                        case '1':
                        case 'true':
                            $converted_value = true;
                            break;
                        default:
                            $this->command->inputErrors[] = 'Arg value[' . $value . '] is not 0|1|true|false.';
                    }
                } elseif ($json) {
                    $converted_value = json_decode($value);
                    if ($converted_value === null) {
                        $this->command->inputErrors[] = 'Arg value[' . $value . '] is not valid JSON.';
                    }
                }
            }
        }

        if ($this->command->inputErrors) {
            foreach ($this->command->inputErrors as $msg) {
                $this->environment->echoMessage(
                    $this->environment->format($msg, 'hangingIndent'),
                    'notice'
                );
            }
            // This command's help text.
            $this->environment->echoMessage("\n" . $this->command);
            exit;
        }
        // Display command and the arg values used.---------------------
        if (!$this->command->preConfirmed) {
            $this->environment->echoMessage(
                $this->environment->format(
                    $this->environment->format($this->command->name, 'emphasize')
                    . "\n" . 'store: ' . $store
                    . "\n" . 'section: ' . $section
                    . "\n" . 'key: ' . $key
                    . "\n" . 'value: ' . addcslashes($value, "\0..\37")
                    . (!$this->command->options ? '' : ("\n--" . join(' --', array_keys($this->command->options)))),
                    'hangingIndent'
                )
            );
        }
        // Request confirmation, unless user used the --yes/-y option.
        if (
            !$this->command->preConfirmed
            && !$this->environment->confirm(
                'Set that config item? Type \'yes\' or \'y\' to continue:',
                ['yes', 'y'],
                '',
                'Aborted setting config item.'
            )
        ) {
            exit;
        }
        // Check if the command is doable.------------------------------
        // Nothing to check here.
        $config_class = static::CLASS_CONFIG;
        /** @var IniSectionedConfig $config_store */
        $config_store = forward_static_call_array($config_class . '::getInstance', [$store]);
        // Do it.
        if (!$config_store->set($section, $key, $converted_value)) {
            $this->environment->echoMessage(
                'Failed to set config item store[' . $store
                . '] section[' . $section . '] key[' . $key . '] value[' . addcslashes($value, "\0..\37") . '].',
                'error'
            );
        } else {
            $this->environment->echoMessage(
                'Set config item store[' . $store
                . '] section[' . $section . '] key[' . $key . '] value[' . addcslashes($value, "\0..\37") . '].',
                'success'
            );
        }
        exit;
    }

    /**
     * @return void
     *      Exits.
     */
    protected function cmdDelete() /*: void*/
    {
        // Validate input. ---------------------------------------------
        $store = '';
        if (empty($this->command->arguments['store'])) {
            $this->command->inputErrors[] = !isset($this->command->arguments['store']) ? 'Missing \'store\' argument.' :
                'Empty \'store\' argument.';
        } else {
            $store = $this->command->arguments['store'];
            if (!ConfigKey::validate($store)) {
                $this->command->inputErrors[] = 'Invalid \'store\' argument.';
            }
        }
        $section = '';
        if (empty($this->command->arguments['section'])) {
            $this->command->inputErrors[] = !isset($this->command->arguments['section']) ?
                'Missing \'section\' argument.' : 'Empty \'section\' argument.';
        } else {
            $section = $this->command->arguments['section'];
            if (!ConfigKey::validate($section)) {
                $this->command->inputErrors[] = 'Invalid \'section\' argument.';
            }
        }
        $key = '';
        if (empty($this->command->arguments['key'])) {
            $this->command->inputErrors[] = !isset($this->command->arguments['key']) ?
                'Missing \'key\' argument.' : 'Empty \'key\' argument.';
        } else {
            $key = $this->command->arguments['key'];
            if (!ConfigKey::validate($key)) {
                $this->command->inputErrors[] = 'Invalid \'key\' argument.';
            }
        }

        if ($this->command->inputErrors) {
            foreach ($this->command->inputErrors as $msg) {
                $this->environment->echoMessage(
                    $this->environment->format($msg, 'hangingIndent'),
                    'notice'
                );
            }
            // This command's help text.
            $this->environment->echoMessage("\n" . $this->command);
            exit;
        }
        // Display command and the arg values used.---------------------
        if (!$this->command->preConfirmed) {
            $this->environment->echoMessage(
                $this->environment->format(
                    $this->environment->format($this->command->name, 'emphasize')
                    . "\n" . 'store: ' . $store
                    . "\n" . 'section: ' . $section
                    . "\n" . 'key: ' . $key,
                    'hangingIndent'
                )
            );
        }
        // Request confirmation, unless user used the --yes/-y option.
        if (
            !$this->command->preConfirmed
            && !$this->environment->confirm(
                'Delete that config item? Type \'yes\' or \'y\' to continue:',
                ['yes', 'y'],
                '',
                'Aborted deleting config item.'
            )
        ) {
            exit;
        }
        // Check if the command is doable.------------------------------
        // Nothing to check here.
        $config_class = static::CLASS_CONFIG;
        /** @var IniSectionedConfig $config_store */
        $config_store = forward_static_call_array($config_class . '::getInstance', [$store]);
        // Do it.
        if (!$config_store->delete($section, $key)) {
            $this->environment->echoMessage(
                'Failed to delete config item store[' . $store . '] section[' . $section . '] key[' . $key . '].',
                'error'
            );
        } else {
            $this->environment->echoMessage(
                'Deleted config item store[' . $store . '] section[' . $section . '] key[' . $key . '].',
                'success'
            );
        }
        exit;
    }

    /**
     * Ignores pre-confirmation --yes/-y option.
     *
     * @return void
     *      Exits.
     */
    protected function cmdRefresh() /*: void*/
    {
        // Validate input. ---------------------------------------------
        $store = '';
        if (empty($this->command->arguments['store'])) {
            $this->command->inputErrors[] = !isset($this->command->arguments['store']) ? 'Missing \'store\' argument.' :
                'Empty \'store\' argument.';
        } else {
            $store = $this->command->arguments['store'];
            if (!ConfigKey::validate($store)) {
                $this->command->inputErrors[] = 'Invalid \'store\' argument.';
            }
        }
        // Pre-confirmation --yes/-y ignored for this command.
        if ($this->command->preConfirmed) {
            $this->command->inputErrors[] = 'Pre-confirmation \'yes\'/-y option not supported for this command.';
        }
        if ($this->command->inputErrors) {
            foreach ($this->command->inputErrors as $msg) {
                $this->environment->echoMessage(
                    $this->environment->format($msg, 'hangingIndent'),
                    'notice'
                );
            }
            // This command's help text.
            $this->environment->echoMessage("\n" . $this->command);
            exit;
        }
        // Display command and the arg values used.---------------------
        $this->environment->echoMessage(
            $this->environment->format(
                $this->environment->format($this->command->name, 'emphasize')
                . "\n" . 'store: ' . $store,
                'hangingIndent'
            )
        );
        // Request confirmation, ignore --yes/-y pre-confirmation option.
        if (
            !$this->environment->confirm(
                'Refresh that config store? Type \'yes\' to continue:',
                ['yes'],
                '',
                'Aborted refreshing config store.'
            )
        ) {
            exit;
        }
        // Check if the command is doable.------------------------------
        // Nothing to check here.
        $config_class = static::CLASS_CONFIG;
        /** @var IniSectionedConfig $config_store */
        $config_store = forward_static_call_array($config_class . '::getInstance', [$store]);
        // Do it.
        if (!$config_store->refresh()) {
            $this->environment->echoMessage('Failed to refresh config store[' . $store . '].', 'error');
        } else {
            $this->environment->echoMessage('Refreshed config store[' . $store . '].', 'success');
        }
        exit;
    }


    // CliCommandInterface.-----------------------------------------------------

    /**
     * @return string
     */
    public function commandProviderAlias(): string
    {
        return static::COMMAND_PROVIDER_ALIAS;
    }

    /**
     * @param CliCommand $command
     *
     * @return mixed
     *      Return value of the executed command, if any.
     *      May well exit.
     *
     * @throws \LogicException
     *      If the command mapped by CliEnvironment
     *      isn't this provider's command.
     */
    public function executeCommand(CliCommand $command)
    {
        $this->command = $command;
        $this->environment = CliEnvironment::getInstance();

        switch ($command->name) {
            case static::COMMAND_PROVIDER_ALIAS . '-get':
                return $this->cmdGet();
            case static::COMMAND_PROVIDER_ALIAS . '-set':
                $this->cmdSet();
                exit;
            case static::COMMAND_PROVIDER_ALIAS . '-delete':
                $this->cmdDelete();
                exit;
            case static::COMMAND_PROVIDER_ALIAS . '-refresh':
                $this->cmdRefresh();
                exit;
            default:
                throw new \LogicException(
                    'Command named[' . $command->name . '] is not provided by class[' . get_class($this) . '].'
                );
        }
    }
}
