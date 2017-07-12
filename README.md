## Config ##

#### Requirements ####

- PHP >=7.0
- [SimpleComplex Cache](https://github.com/simplecomplex/php-cache)
- [SimpleComplex Utils](https://github.com/simplecomplex/php-utils)

##### Suggestions #####

- [SimpleComplex Inspect](https://github.com/simplecomplex/inspect) (for CLI)

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

The ini-file based classes parses ini-files, and saves to cache stores.

They read recursively from their ini-file paths.
That allows one to clone and use ini-files from multiple version control repositories.

> Ini-files are so old-school...

Yep, but they are less error-prone than JSON, YAML, what-not.  
The syntax is so simple it's hard to make mistakes. And operations people are used to ini-files.

##### ``` IniConfig ``` #####  
is not sectioned. Simple but probably not that useful.

**``` IniSectionedConfig ```**  
is a powerful general usage implementation.

Reads ini-files from a _base_ path and an _override_ path.  
Keep development/production invariant variables (ini-files) in the _base_ path.  
Let operations keep production variables in the _override_ path.

Using the list-principle - and fully supporting ``` remember() ``` and ``` forget() ``` -
``` IniSectionedConfig ```is optimal for accessing many/all keys of a section within a limited procedure.
