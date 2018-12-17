## Config ##

- [Installation](#installation)
- [Requirements](#requirements)

### Simple and sectioned configuration interfaces ###

**``` ConfigInterface ```**  
is basically PSR-16 Simple Cache without time-to-live.  
There's a key and a value.

That principle isn't optimal for general use, unless you like real long and complex key names.  

**``` SectionedConfigInterface ```**  
Splits the item key into 'section' plus 'key', facilitating a more organized structure.

The immediate benefit is that you can namespace all items that belong to a particular library.

The section-key pattern can be implemented and utilized in two manners:

- concatenation: the configuration setters and getters simply concatenate section and key
- listing: the section is a list of keys, you can access the whole section as well as a particular key

``` SectionedConfigInterface ``` also allows for short-term keeping items in memory,
via methods ``` remember() ``` and ``` forget() ```.

### Implementations ###

#### Environment variable based ####

**``` EnvConfig ```**  
is a simple abstraction of server environment variables.

**``` EnvSectionedConfig ```**  
a sectioned implementation, using concatenation.

#### Ini-files as source, cache as store ####

The ini-file based classes parse ini-files, and save to cache stores.

They read recursively from their ini-file paths.
That allows one to clone and use ini-files from multiple version control repositories.

> Ini-files are so old-school...

Aye, but the ini format is less error-prone than JSON, YAML, what-not.  
The syntax is so simple it's hard to make mistakes. And operations people are used to ini-files.

##### Cache layer #####
is [SimpleComplex Cache](https://github.com/simplecomplex/php-cache) **``` PersistentFileCache ```**.  
Cache store names are prefixed with **``` 'config.' ```**  
Beware of conflict; do not prefix other cache stores that way. 

##### Types of ini-based configuration #####

**``` IniConfig ```**  
is not sectioned. Simple but probably not that useful.  
``` $value = $config->get('key') ```

**``` IniSectionedConfig ```**  
is a powerful general usage implementation.  
``` $value = $config->get('section', 'key') ```

Reads ini-files from a _base_ path and an _override_ path.  
Keep development/production invariant variables (ini-files) in the _base_ path.  
Let operations keep production variables in the _override_ path.

Using the list-principle - and fully supporting ``` remember() ``` and ``` forget() ``` -
``` IniSectionedConfig ``` is optimal for accessing many/all keys of a section within a limited procedure.

**``` IniSectionedFlatConfig ```**  
a sectioned implementation, using concatenation.

Optimal for types of configuration one expects to access keys of diverse sections in an unpredictable manner,
but still want the organisational benefit of sections; many but exact cache reads.  
[SimpleComplex Locale](https://github.com/simplecomplex/php-locale) uses this config class for localized texts.

### Abstraction ###

The **``` Config ```** class is an abstraction of sectioned configuration.

In this package it extends ``` IniSectionedConfig ```.  
In an extending package it could  be some other sectioned config implementation.

### CLI interface ###

**``` CliConfig ```**  delivers CLI commands for setting, getting and deleting config items.  
And commands for refreshing and exporting full configuration stores.

It exposes ``` IniSectionedConfig ``` instances, via the ``` Config ``` class.  
The other config classes are not accessible via CLI.

### Global config ###

``` Config ``` defaults to deliver an instance named 'global'.

A typical system could probably benefit from a single config instance for the bulk of items.  
Since the whole thing _runtime_ is cache based, there's no performance reason for using multiple instances.

#### Dependency injection container ID: config ####

Recommendation: access (and thus instantiate) the global config via DI container ID 'config'.  
See [SimpleComplex Utils](https://github.com/simplecomplex/php-utils) ``` Dependency ```.

### Example ###

```php
// Bootstrap.
Dependency::genericSetMultiple([
    'cache-broker' => function () {
        return new \SimpleComplex\Cache\CacheBroker();
    },
    'config' => function() {
        return new \SimpleComplex\Config\Config('global');
    },
]);
// ...
// Use.
/** @var \Psr\Container\ContainerInterface $container */
$container = Dependency::container();
/**
 * Create or re-initialize the 'global' config store;
 * based on ini-files placed in base and override paths,
 * cached by a PSR-16 Simple Cache cache store.
 *
 * @var \SimpleComplex\Config\IniSectionedConfig $config
 */
$config = $container->get('config');
/** @var mixed $whatever */
$whatever = $config->get('some-section', 'some-key', 'the default value');
```

### CLI commands ###

```bash
# List all config commands and their help.
php cli.php config -h
# One command's help.
php cli.php config-xxx -h

# List existing config stores.
php cli.php config-list-stores

# Display/get value of a config item.
php cli.php config-get store section key

# Set a config item.
php cli.php config-set store section key value

# Delete a config item.
php cli.php config-delete store section key

# Refresh a config store from .ini file sources.
# The fresh store gets applied atomically, when fully built.
php cli.php config-refresh store

# Export a config store as JSON to a file.
php cli.php config-export store target-path-and-file
```

### Installation ###

Create a 'conf' directory alongside the document root dir.

Like:  
```/var/www/my-host/```**```http```**  
```/var/www/my-host/```**```conf```**

Create 'base' and 'override' paths within the 'conf', like:  
```conf/```**```ini/base```**  
```conf/```**```ini/override```**  

For the 'global' config store, place or symlink or git clone your system's  
.ini configuration files under the 'base' and 'override' paths  
using file extension ```global.ini``` (= ```[store name].ini```).

Like:  
```conf/ini/base/```**```some-ding.global.ini```**  
```conf/ini/override/```**```some-ding.prod.global.ini```**  

If that directory structure isn't suitable, do either:
- supply **```Config```** constructor with a 'paths' argument
- extend **```Config```** and override it's class constant **```PATH_DEFAULTS```**


### Requirements ###

- PHP >=7.0
- [SimpleComplex Cache](https://github.com/simplecomplex/php-cache)
- [SimpleComplex Utils](https://github.com/simplecomplex/php-utils)

##### Suggestions #####

- [SimpleComplex Inspect](https://github.com/simplecomplex/inspect)
