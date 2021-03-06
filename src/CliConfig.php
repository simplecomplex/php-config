<?php
/**
 * SimpleComplex PHP Config
 * @link      https://github.com/simplecomplex/php-config
 * @copyright Copyright (c) 2014-2017 Jacob Friis Mathiasen
 * @license   https://github.com/simplecomplex/php-config/blob/master/LICENSE (MIT License)
 */
declare(strict_types=1);

namespace SimpleComplex\Config;

use SimpleComplex\Utils\Interfaces\CliCommandInterface;
use SimpleComplex\Utils\CliEnvironment;
use SimpleComplex\Utils\CliCommand;
use SimpleComplex\Utils\Dependency;
use SimpleComplex\Utils\Utils;
use SimpleComplex\Cache\CacheBroker;

/**
 * CLI only.
 *
 * Expose/execute config commands.
 *
 * @see simplecomplex_config_cli()
 *
 * @see IniSectionedConfig::get()
 * @see IniSectionedConfig::set()
 * @see IniSectionedConfig::delete()
 * @see IniConfigBase::refresh()
 *
 * @code
 * # CLI
 * cd vendor/simplecomplex/utils/src/cli
 * php cli.php config -h
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
     * @var string
     */
    const CLASS_CONFIG = Config::class;

    /**
     * @var string
     */
    const CLASS_CACHE_BROKER = CacheBroker::class;

    /**
     * @var string
     */
    const CLASS_INSPECT = '\\SimpleComplex\\Inspect\\Inspect';

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
                static::COMMAND_PROVIDER_ALIAS . '-list-stores',
                'List all config stores.',
                [
                    'match' => 'Regex to match against config store names. Optional.',
                ],
                [
                    'get' => 'Return comma-separated list, don\'t print.',
                ],
                [
                    'g' => 'get',
                ]
            ),
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
                    'get' => 'Return value, don\'t print it.',
                    'inspect' => 'Print Inspect\'ed value instead of JSON-encoded.',
                ],
                [
                    'a' => 'all',
                    'g' => 'get',
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
                    'array' => '(with \'json\' option) Set object value as array.',
                ],
                [
                    'i' => 'int',
                    'f' => 'float',
                    'b' => 'bool',
                    'j' => 'json',
                    'r' => 'array',
                ]
            ),
            new CliCommand(
                $this,
                static::COMMAND_PROVIDER_ALIAS . '-set-sub',
                'Set a config sub item, an array|object bucket.',
                [
                    'store' => 'Config store name.',
                    'section' => 'Config section.',
                    'key' => 'Config item key.',
                    'sub' => 'Config sub item key.',
                    'value' => 'Value to set, please enclose in single quotes'
                        . "\n" . 'when not non-negative int/float or boolean int.',
                ],
                [
                    'int' => 'Set as integer.',
                    'float' => 'Set as float.',
                    'bool' => 'Set as boolean, use 0|1|true|false for arg value.',
                    'json' => 'Arg value is JSON-encoded.',
                    'array' => '(with \'json\' option) Set object value as array.',
                ],
                [
                    'i' => 'int',
                    'f' => 'float',
                    'b' => 'bool',
                    'j' => 'json',
                    'r' => 'array',
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
                'Refreshes the config store\'s cache store,'
                . ' loading fresh configuration from all .ini files in the base and override paths.'
                . "\n" . 'The fresh store gets applied atomically, when fully built.'
                . "\n" . 'NB: All items that have been set, overwritten or deleted since last refresh'
                . ' will be gone or restored to .ini-files\' original state.',
                [
                    'store' => 'Config store name.',
                ],
                [
                    'allow-none' => 'Allow no configuration at all.',
                    'verbose' => 'List source .ini-files used.',
                ],
                [
                    'z' => 'allow-none',
                    'v' => 'verbose',
                ]
            ),
            new CliCommand(
                $this,
                static::COMMAND_PROVIDER_ALIAS . '-export',
                'Export configuration from cache or from sources.',
                [
                    'store' => 'Config store name.',
                    'target-file' => 'Path and filename; the path must exist already.'
                        . "\n" . 'Relative is relative to document root.',
                ],
                [
                    'from-sources' => 'From source paths\' ini files; not cache.',
                    'format' => 'JSON; default, and the only format supported.',
                    'unescaped' => 'Don\'t escape slash, tag, quotes, ampersand, unicode chars.',
                    'pretty' => 'Pretty-print.',
                ],
                [
                    's' => 'from-sources',
                    'u' => 'unescaped',
                    'p' => 'pretty',
                ]
            )
        );
    }

    /**
     * @var CliCommand
     */
    protected $command;

    /**
     * @var CliEnvironment
     */
    protected $environment;

    /**
     * List all config stores.
     *
     * A copy of CliCache, except for further check that the cache store name
     * starts with 'config.'.
     *
     * @see \SimpleComplex\Cache\CliCache
     *
     * @return mixed
     *      Exits if no/falsy option 'get'.
     */
    protected function cmdListStores() /*: void*/
    {
        /**
         * @see simplecomplex_cache_cli()
         */
        $container = Dependency::container();
        // Validate input. ---------------------------------------------
        $match = '';
        if (
            !empty($this->command->arguments['match'])
            && ($match = trim($this->command->arguments['match'])) !== ''
            && !preg_match('/^\/.+\/[a-zA-Z]*$/', $match)
        ) {
            $this->command->inputErrors[] = '\'match\' argument must be slash delimited regular expression.';
        }

        $get = !empty($this->command->options['get']);

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
        // No arg values to list.
        // Check if the command is doable.------------------------------
        // Does that/these store(s) exist?
        if ($container->has('cache-broker')) {
            /** @var CacheBroker $cache_broker */
            $cache_broker_class = get_class($container->get('cache-broker'));
        } else {
            $cache_broker_class = static::CLASS_CACHE_BROKER;
        }
        $cache_class = constant($cache_broker_class . '::CACHE_CLASSES')[CacheBroker::CACHE_BASE];
        if (!method_exists($cache_class, 'listInstances')) {
            $this->environment->echoMessage('Cannot retrieve list of cache store instances via class['
                . $cache_class . '], has no static method listInstances().', 'error');
            exit;
        }
        $stores = forward_static_call($cache_class . '::listInstances');

        // Do it.
        $names = [];
        foreach ($stores as $instance) {
            if (preg_match('/^config\..+/', $instance->name)) {
                $name = substr($instance->name, 7);
                if (!$match || preg_match($match, $name)) {
                    $names[] = $name;
                }
            }
        }
        sort($names);
        if ($get) {
            return join(',', $names);
        }
        $this->environment->echoMessage(join("\n", $names));
        exit;
    }

    /**
     * @return mixed
     *      Exits if no/falsy option 'get'.
     */
    protected function cmdGet()
    {
        /**
         * @see simplecomplex_config_cli()
         */
        $container = Dependency::container();
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

        $get = !empty($this->command->options['get']);
        $use_inspect = !$get && !empty($this->command->options['inspect']);

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
        if (!$get) {
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
        if ($store == 'global' && $container->has('config')) {
            /** @var IniSectionedConfig $config_store */
            $config_store = $container->get('config');
        } else {
            $config_class = static::CLASS_CONFIG;
            /** @var IniSectionedConfig $config_store */
            $config_store = new $config_class($store);
        }
        // Do it.
        if ($all_keys) {
            if (!$config_store->has($section, '*')) {
                $this->environment->echoMessage('');
                $this->environment->echoMessage(
                    'Config store[' . $store . '] section[' . $section . '] doesn\'t exist.',
                    'notice'
                );
                exit;
            }
            $value = $config_store->get($section, '*');
        } else {
            if (!$config_store->has($section, $key)) {
                $this->environment->echoMessage('');
                $this->environment->echoMessage(
                    'Config store[' . $store . '] section[' . $section . '] key[' . $key . '] doesn\'t exist.',
                    'notice'
                );
                exit;
            }
            $value = $config_store->get($section, $key);
        }
        if ($get) {
            return $value;
        }
        $this->environment->echoMessage('');
        if ($use_inspect) {
            $inspect = null;
            if ($container->has('inspect')) {
                $inspect = $container->get('inspect');
            } elseif (class_exists(static::CLASS_INSPECT)) {
                $class_inspect = static::CLASS_INSPECT;
                $inspect = new $class_inspect($container->has('config') ? $container->get('config') : null);
            }
            if ($inspect) {
                $this->environment->echoMessage($inspect->inspect($value)->toString(true));
                exit;
            }
        }
        $this->environment->echoMessage(
            json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );
        exit;
    }

    /**
     * @return void
     *      Exits.
     */
    protected function cmdSet() /*: void*/
    {
        /**
         * @see simplecomplex_config_cli()
         */
        $container = Dependency::container();
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
        $array = $json && !empty($this->command->options['array']);

        if (((int) $int + (int) $float + (int) $bool + (int) $json) > 1) {
            $this->command->inputErrors[] = 'Cannot use more than a single value type option.';
        } else {
            if (!isset($this->command->arguments['value'])) {
                $this->command->inputErrors[] = !isset($this->command->arguments['value']) ?
                    'Missing \'value\' argument.' : 'Empty \'value\' argument.';
            } else {
                $converted_value = $value = $this->command->arguments['value'];
                if ($int) {
                    if (!ctype_digit('' . $value)) {
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
                    elseif ($array && is_object($converted_value)) {
                        $converted_value = (array) $converted_value;
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
        if ($store == 'global' && $container->has('config')) {
            /** @var IniSectionedConfig $config_store */
            $config_store = $container->get('config');
        } else {
            $config_class = static::CLASS_CONFIG;
            /** @var IniSectionedConfig $config_store */
            $config_store = new $config_class($store);
        }
        // Do it.
        if (!$config_store->set($section, $key, $converted_value)) {
            $this->environment->echoMessage(
                'Failed to set config item store[' . $store
                . '] section[' . $section . '] key[' . $key . '] value[' . addcslashes($value, "\0..\37") . '].',
                'error'
            );
        } elseif (!$this->command->silent) {
            $this->environment->echoMessage(
                'Set config item store[' . $store
                . '] section[' . $section . '] key[' . $key . '] value[' . addcslashes($value, "\0..\37") . ']'
                . ($json && $array ? ' as array' : '') . '.',
                'success'
            );
        }
        exit;
    }

    /**
     * @return void
     *      Exits.
     */
    protected function cmdSetSub() /*: void*/
    {
        /**
         * @see simplecomplex_config_cli()
         */
        $container = Dependency::container();
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
        $sub = '';
        if (empty($this->command->arguments['sub'])) {
            $this->command->inputErrors[] = !isset($this->command->arguments['sub']) ?
                'Missing \'sub\' argument.' : 'Empty \'sub\' argument.';
        } else {
            $sub = $this->command->arguments['sub'];
        }

        $converted_value = $value = '';
        $int = !empty($this->command->options['int']);
        $float = !empty($this->command->options['float']);
        $bool = !empty($this->command->options['bool']);
        $json = !empty($this->command->options['json']);
        $array = $json && !empty($this->command->options['array']);

        if (((int) $int + (int) $float + (int) $bool + (int) $json) > 1) {
            $this->command->inputErrors[] = 'Cannot use more than a single value type option.';
        } else {
            if (!isset($this->command->arguments['value'])) {
                $this->command->inputErrors[] = !isset($this->command->arguments['value']) ?
                    'Missing \'value\' argument.' : 'Empty \'value\' argument.';
            } else {
                $converted_value = $value = $this->command->arguments['value'];
                if ($int) {
                    if (!ctype_digit('' . $value)) {
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
                    elseif ($array && is_object($converted_value)) {
                        $converted_value = (array) $converted_value;
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
                    . "\n" . 'sub: ' . $sub
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
                'Set that config sub item? Type \'yes\' or \'y\' to continue:',
                ['yes', 'y'],
                '',
                'Aborted setting config sub item.'
            )
        ) {
            exit;
        }
        // Check if the command is doable.------------------------------
        // Nothing to check here.
        if ($store == 'global' && $container->has('config')) {
            /** @var IniSectionedConfig $config_store */
            $config_store = $container->get('config');
        } else {
            $config_class = static::CLASS_CONFIG;
            /** @var IniSectionedConfig $config_store */
            $config_store = new $config_class($store);
        }
        // Do it.
        $deep_array = $config_store->get($section, $key, []);
        if (!is_array($deep_array) && !is_object($deep_array)) {
            $this->environment->echoMessage(
                'Cannot set config sub item because existing item key[' . $key . '] type[' . Utils::getType($deep_array)
                . '] is not array|object, store[' . $store
                . '] section[' . $section . '] key[' . $key . '] sub[' . $sub . '] value[' . addcslashes($value, "\0..\37") . '].',
                'error'
            );
            exit;
        }
        $deep_array[$sub] = $converted_value;

        if (!$config_store->set($section, $key, $deep_array)) {
            $this->environment->echoMessage(
                'Failed to set config sub item store[' . $store
                . '] section[' . $section . '] key[' . $key . '] sub[' . $sub . '] value[' . addcslashes($value, "\0..\37") . '].',
                'error'
            );
        } elseif (!$this->command->silent) {
            $this->environment->echoMessage(
                'Set config sub item store[' . $store
                . '] section[' . $section . '] key[' . $key . '] sub[' . $sub . '] value[' . addcslashes($value, "\0..\37") . ']'
                . ($json && $array ? ' as array' : '') . '.',
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
        /**
         * @see simplecomplex_config_cli()
         */
        $container = Dependency::container();
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
        if ($store == 'global' && $container->has('config')) {
            /** @var IniSectionedConfig $config_store */
            $config_store = $container->get('config');
        } else {
            $config_class = static::CLASS_CONFIG;
            /** @var IniSectionedConfig $config_store */
            $config_store = new $config_class($store);
        }
        // Do it.
        if (!$config_store->delete($section, $key)) {
            $this->environment->echoMessage(
                'Failed to delete config item store[' . $store . '] section[' . $section . '] key[' . $key . '].',
                'error'
            );
        } elseif (!$this->command->silent) {
            $this->environment->echoMessage(
                'Deleted config item store[' . $store . '] section[' . $section . '] key[' . $key . '].',
                'success'
            );
        }
        exit;
    }

    /**
     * Ignores pre-confirmation --yes/-y option,
     * unless .risky_command_skip_confirm file placed in document root.
     *
     * @return void
     *      Exits.
     */
    protected function cmdRefresh() /*: void*/
    {
        /**
         * @see simplecomplex_config_cli()
         */
        $container = Dependency::container();
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
        $allow_none = !empty($this->command->options['allow-none']);
        $verbose = !empty($this->command->options['verbose']);
        // Pre-confirmation --yes/-y ignored for this command.
        if ($this->environment->riskyCommandRequireConfirm && $this->command->preConfirmed) {
            $this->command->inputErrors[] = 'Pre-confirmation \'yes\'/-y option not supported for this command,'
                . "\n" . 'unless env var PHP_LIB_SIMPLECOMPLEX_UTILS_CLI_SKIP_CONFIRM'
                . "\n" . 'or .risky_command_skip_confirm file in document root.';
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
        if ($this->environment->riskyCommandRequireConfirm || !$this->command->preConfirmed) {
            $this->environment->echoMessage(
                $this->environment->format(
                    $this->environment->format($this->command->name, 'emphasize')
                    . "\n" . 'store: ' . $store,
                    'hangingIndent'
                )
            );
        }
        // Request confirmation, ignore --yes/-y pre-confirmation option;
        // unless .risky_command_skip_confirm file placed in document root.
        if ($this->environment->riskyCommandRequireConfirm) {
            if (!$this->environment->confirm(
                'Refresh that config store? Type \'yes\' to continue:',
                ['yes'],
                '',
                'Aborted refreshing config store.'
            )) {
                exit;
            }
        } elseif (!$this->command->preConfirmed && !$this->environment->confirm(
                'Refresh that config store? Type \'yes\' or \'y\' to continue:',
                ['yes', 'y'],
                '',
                'Aborted refreshing config store.'
            )) {
            exit;
        }
        // Check if the command is doable.------------------------------
        // Nothing to check here.
        if ($store == 'global' && $container->has('config')) {
            /** @var IniSectionedConfig $config_store */
            $config_store = $container->get('config');
        } else {
            $config_class = static::CLASS_CONFIG;
            /** @var IniSectionedConfig $config_store */
            $config_store = new $config_class($store);
        }
        // Do it.
        if (!$config_store->refresh($allow_none, $verbose)) {
            $this->environment->echoMessage('Failed to refresh config store[' . $store . '].', 'error');
        } elseif (!$this->command->silent) {
            $this->environment->echoMessage('Refreshed config store[' . $store . '].', 'success');
        }
        exit;
    }

    /**
     * Ignores pre-confirmation --yes/-y option,
     * unless .risky_command_skip_confirm file placed in document root.
     *
     * @return void
     *      Exits.
     */
    protected function cmdExport() /*: void*/
    {
        /**
         * @see simplecomplex_config_cli()
         */
        $container = Dependency::container();
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
        $target_file = '';
        if (empty($this->command->arguments['target-file'])) {
            $this->command->inputErrors[] = !isset($this->command->arguments['target-file']) ?
                'Missing \'target-file\' argument.' : 'Empty \'target-file\' argument.';
        } else {
            $target_file = $this->command->arguments['target-file'];
        }

        $from_sources = !empty($this->command->options['from-sources']);
        $format = !empty($this->command->options['format']) ? $this->command->options['format'] : 'JSON';
        $unescaped = !empty($this->command->options['unescaped']);
        $pretty = !empty($this->command->options['pretty']);

        // Pre-confirmation --yes/-y ignored for this command.
        if ($this->environment->riskyCommandRequireConfirm && $this->command->preConfirmed) {
            $this->command->inputErrors[] = 'Pre-confirmation \'yes\'/-y option not supported for this command,'
                . "\n" . 'unless env var PHP_LIB_SIMPLECOMPLEX_UTILS_CLI_SKIP_CONFIRM'
                . "\n" . 'or .risky_command_skip_confirm file in document root.';
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
        if ($this->environment->riskyCommandRequireConfirm || !$this->command->preConfirmed) {
            $this->environment->echoMessage(
                $this->environment->format(
                    $this->environment->format($this->command->name, 'emphasize')
                    . "\n" . 'store: ' . $store
                    . "\n" . 'target-file: ' . $target_file
                    . (!$this->command->options ? '' : ("\n--" . join(' --', array_keys($this->command->options)))),
                    'hangingIndent'
                )
            );
        }
        // Request confirmation, ignore --yes/-y pre-confirmation option;
        // unless .risky_command_skip_confirm file placed in document root.
        if ($this->environment->riskyCommandRequireConfirm) {
            if (!$this->environment->confirm(
                'Export that config store from ' . (!$from_sources ? 'cache' : 'sources')
                . ' - will overwrite the target file (if exists)?'
                . "\n" . 'Type \'yes\' to continue:',
                ['yes'],
                '',
                'Aborted exporting config store.'
            )) {
                exit;
            }
        } elseif (!$this->command->preConfirmed && !$this->environment->confirm(
                'Export that config store from ' . (!$from_sources ? 'cache' : 'sources')
                . ' - will overwrite the target file (if exists)?'
                . "\n" . 'Type \'yes\' or \'y\' to continue:',
                ['yes', 'y'],
                '',
                'Aborted exporting config store.'
            )) {
            exit;
        }
        // Check if the command is doable.------------------------------
        // Nothing to check here.
        if ($store == 'global' && $container->has('config')) {
            /** @var IniSectionedConfig $config_store */
            $config_store = $container->get('config');
        } else {
            $config_class = static::CLASS_CONFIG;
            /** @var IniSectionedConfig $config_store */
            $config_store = new $config_class($store);
        }
        // Do it.
        if (!$config_store->export(
            $target_file,
            [
                'fromSources' => $from_sources,
                'format' => strtoupper($format),
                'unescaped' => $unescaped,
                'pretty' => $pretty,
            ]
        )) {
            $this->environment->echoMessage('Failed to export config store[' . $store . '].', 'error');
        } elseif (!$this->command->silent) {
            $this->environment->echoMessage(
                'Exported config store[' . $store . '] from ' . (!$from_sources ? 'cache' : 'sources')
                . ' to target file[' . $target_file . '].',
                'success'
            );
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
            case static::COMMAND_PROVIDER_ALIAS . '-list-stores':
                return $this->cmdListStores();
            case static::COMMAND_PROVIDER_ALIAS . '-get':
                return $this->cmdGet();
            case static::COMMAND_PROVIDER_ALIAS . '-set':
                $this->cmdSet();
                exit;
            case static::COMMAND_PROVIDER_ALIAS . '-set-sub':
                $this->cmdSetSub();
                exit;
            case static::COMMAND_PROVIDER_ALIAS . '-delete':
                $this->cmdDelete();
                exit;
            case static::COMMAND_PROVIDER_ALIAS . '-refresh':
                $this->cmdRefresh();
                exit;
            case static::COMMAND_PROVIDER_ALIAS . '-export':
                $this->cmdExport();
                exit;
            default:
                throw new \LogicException(
                    'Command named[' . $command->name . '] is not provided by class[' . get_class($this) . '].'
                );
        }
    }
}
