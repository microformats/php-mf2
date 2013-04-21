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

include '/vendor/autoload.php';

use mf2\Parser;

$parser = new Parser('<div class="h-card"><p class="p-name">Barnaby Walters</p></div>');
$output = $parser->parse();

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

If no microformats are found, `items` will be an empty array.

Note that, whilst the property prefixes are stripped, the prefix of the `h-*` classname is left on.

A baseurl can be provided as the second parameter of `mf2\Parser::__construct()` — it’s prepended to any `u-` properties which are relative URLs.

### Classic Microformat/Classmap Markup Support

php-mf2 has limited support for classic microformats — it doesn’t actually parse
them but can convert legacy classnames into µf2 classnames (e.g. `vcard` =>
`h-card`, `fn` => `p-name`, etc.).

```php
// Once your parser has been initialised:
$parser->convertLegacy(); // Converts classic microformats by default
$out = $parser->parse();
```

You can also define your own custom class mappings, to provide some support for
popular sites which don’t use mf2 but do use use semantic classnames. An experimental
set for twitter.com is provided.

```php
// Once your have $parser
$parser->convertTwitter(); // Adds twitter mapping

// Or, add your own mapping:
$parser->addClassMap([
    'oldclassname' => 'p-new-class-name'
]);

$parser->convertLegacy();

// Then parse
$out = $parser->parse();
```

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
Tests are written in phpunit and are contained within `/tests/`. Running <kbd>phpunit .</kbd> from the root dir will run them all.

There are enough tests to warrant putting them into separate suites for maintenance. The different suits are:

* `ParserTest.php`: Tests for internal, `e-*` parsing and sanity checks.
* `ParseImpliedTest.php`: Tests of the implied property patterns
* `CombinedMicroformatsTest.php`: Tests of nested microformats
* `MicroformatsWikiExamplesTest.php`: Tests taken directly from the wiki pages about µf2
* `Parse*Test.php` for `P`, `U` and `DT`. Contains tests for a particular property type.

As of v0.1.6, the only property with any support for value-class is `dt-*`, so that currently contains the value-class tests. These should be moved elsewhere as value-class and value-title are abstracted and rolled out to all properties.
