php-mf2
=======

php-mf2 is a generic [microformats-2](http://microformats.org/wiki/microformats-2) parser. It doesn’t have a hard-coded list of all the different microformats, just a set of procedures to handle different property types (e.g. `p-` for plaintext, `u-` for URL, etc). This allows for a very small and maintainable parser.

## Installation

Install with [Composer](http://getcomposer.org) by adding `"mf2/mf2": "0.1.*"` to the `require` object in your `composer.json` and running <kbd>php composer.phar update</kbd>.

## Usage

mf2 is PRS-0 autoloadable, so all you have to do to load it is:

1. Include Composer’s auto-generated autoload file (`/vendor/autoload.php`)
1. Declare `mf2\Parser` in your `use` statement
1. Make a `new Parser($input)` where `$input` can either be a string of HTML or a DOMDocument

### Example Code

```php
<?php

include $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use mf2\Parser;

$parser = new Parser('<div class="h-card"><p class="p-name">Barnaby Walters</p></div>');
$output = $parser -> parse();

print_r($output);

// EOF
```

Parser::parse() should return an array structure mirroring the canonical JSON serialisation introduced with µf2. `print_r`ed, it looks something like this:

```
Array
(
    [items] => Array
        (
            [0] => Array
                (
                    [type] => Array
                        (
                            [0] => h-card
                        )
                    [properties] => Array
                    	(
                    		[name] => Barnaby Walters
                    	)

                )

        )

)
```

Note that, whilst the property prefixes are stripped, the prefix of the `h-*` classname is left on.

A baseurl can be provided as the second parameter of `mf2\Parser::__construct()` — it’s prepended to any `u-` properties which are relative URLs.

### Output Types

Different µf-2 property types are returned as different types.

* `h-*` are associative arrays containing more properties
* `p-*` and `u-` are returned as whitespace-trimmed strings
* `dt-*` are returned as \DateTime objects
* `e-*` are returned as **non HTML encoded** strings of markup representing the `innerHTML` of the element classed as `e-*`

### Security

**Little to no filtering of content takes place in mf2\Parser, so treat its output as you would any untrusted data from the source of the parsed document**

## Parsing Behaviour

php-mf2 follows the various µf2 parsing guidelines on the microformats wiki. Useful reference:

* [µf2 prefix parsing guidelines](http://microformats.org/wiki/microformats-2-prefixes)
* [µf2 parsing process](http://microformats.org/wiki/microformats2-parsing)

php-mf2 includes support for implied `p-name`, `u-url` and `u-photo` as per the µf2 parsing process, with the result that **every** microformat **will** have a `name` property whether or not it is explicitly declared. More info on what this is any why it exists in the [µf2 FAQ](http://microformats.org/wiki/microformats-2-faq).

It also includes an approximate implementation of the [Value-Class Pattern](http://microformats.org/wiki/value-class-pattern), currently acting only on `dt-*` properties but soon to be rolled out to all property types

When a DOMElement with a classname of e-\* is found, the DOMNode::C14N() stringvalue of each of it’s children are concatenated and returned

## Testing

**Currently php-mf2 is tested fairly thoroughly, but the code itself is not hugely testable (lots of repetition and redundancy). This is something I’m working on changing**

Tests are written in phpunit and are contained within `/tests/`. Running <kbd>phpunit .</kbd> from the root dir will run them.

Sanity-checks of the basic parsing functions are within `ParserTest.php`, and are organised into groups for each property type (todo: there are now too many, so split these into a file for each property type).

Some of the [value-class pattern tests](http://microformats.org/wiki/value-class-pattern-tests) are contained within `ValueClassTest.php`