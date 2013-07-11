php-mf2
=======

php-mf2 is a generic [microformats-2](http://microformats.org/wiki/microformats-2) parser. It doesn’t have a hard-coded list of all the different microformats, just a set of procedures to handle different property types (e.g. `p-` for plaintext, `u-` for URL, etc). This allows for a very small and maintainable parser.

## Installation

Install with [Composer](http://getcomposer.org) by adding `"mf2/mf2": "0.1.*"` to the `require` object in your `composer.json` and running <kbd>php composer.phar update</kbd>.

## Usage

mf2 is PSR-0 autoloadable, so all you have to do to load it is:

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

If no microformats are found, `items` will be an empty array. rels and alternates are also included.

Note that, whilst the property prefixes are stripped, the prefix of the `h-*` classname is left on.

A baseurl can be provided as the second parameter of `mf2\Parser::__construct()` — it’s prepended to any `u-` properties which are relative URLs.

### Advanced Usage

There are several ways to selectively parse microformats from a document. If you wish to only parse microformats from an element with a particular ID, Parser::parseFromId($id, $htmlSafe=null) is the easiest way.

If your needs are more complex, Parser::parse accepts an optional context DOMNode as it’s third parameter. Typically you’d use Parser::query to run XPath queries on the document to get the element you want to parse from under, then pass it to Parser::parse. Example usage:

```php

$doc = 'More microformats, more microformats <div id="parse-from-here"><span class="h-card">This shows up</span></div> yet more ignored content';
$parser = new Parser($doc);

$parser->parseFromId('parse-from-here'); // returns a document with only the h-card descended from div#parse-from-here

$elementIWant = $parser->query('an xpath query')[0];

$parser->parse(null, $elementIWant); // returns a document with only mfs under the selected element

```

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

There is an issue with the microformats2 parsing spec which can cause the parser output level of HTML-encoding to vary (e.g. some angle brackets are converted to &amp;lt; &amp;gt;, others are not) without the consumer being able to tell at what level any given string is.

To solve this, if you pass true to Parser::parse (or as the third parameter of Parser::__construct), the parser will html-encode angle brackets in any non e-* properties, bringing everything up to the same level of encoding.

Note that this **does not** make content from untrusted sources secure, it merely makes the parser behave in a consistent manner. If you are outputting parsed microformats you must still take security precautions such as purifying the HTML.

## Parsing Behaviour

php-mf2 follows the various µf2 parsing guidelines on the microformats wiki. Useful reference:

* [µf2 prefix parsing guidelines](http://microformats.org/wiki/microformats-2-prefixes)
* [µf2 parsing process](http://microformats.org/wiki/microformats2-parsing)

php-mf2 includes support for implied `p-name`, `u-url` and `u-photo` as per the µf2 parsing process, with the result that **every** microformat **will** have a `name` property whether or not it is explicitly declared. More info on what this is any why it exists in the [µf2 FAQ](http://microformats.org/wiki/microformats-2-faq).

It also includes an approximate implementation of the [Value-Class Pattern](http://microformats.org/wiki/value-class-pattern), currently acting only on `dt-*` properties but soon to be rolled out to all property types

When a DOMElement with a classname of e-\* is found, the DOMNode::C14N() stringvalue of each of it’s children are concatenated and returned

## Contributing

Pull requests very welcome, please try to maintain stylistic, structural and naming consistency with the existing codebase, and don’t be too upset if I make naming changes :)

Please add tests which cover changes you plan to make or have made. I use PHPUnit, which is the de-facto standard for modern PHP development.

At the very least, run the test suite before and after making your changes to make sure you haven’t broken anything.

Issues/bug reports welcome. If you know how to write tests then please do so as code always expresses problems and intent much better than English, and gives me a way of measuring whether or not fixes have actually solved your problem. If you don’t know how to write tests, don’t worry :) Just include as much useful information in the issue as you can.

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

### Changelog

#### v0.1.19 (2013-06-11)

* Required stable version of webigniton/absolute-url-resolver, hopefully resolving versioning problems

#### v0.1.18 (2013-06-05)

* Fixed problems with isElementParsed, causing elements to be incorrectly parsed
* Cleaned up some test files

#### v0.1.17

* Rewrote some PHP 5.4 array syntax which crept into 0.1.16 so php-mf2 still works on PHP 5.3
* Fixed a bug causing weird partial microformats to be added to parent microformats if they had doubly property-nested children
* Finally actually licensed this project under a real license (MIT, in composer.json)
* Suggested barnabywalters/mf-cleaner in composer.json

#### v0.1.16

* Ability to parse from only an ID
* Context DOMElement can be passed to $parse
* Parser::query runs XPath queries on the current document
* When parsing e-* properties, elements with @src, @data or @href have relative URLs resolved in the output

#### v0.1.15

* Added html-safe options
* Added rel+rel-alternate parsing