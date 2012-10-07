php-mf2
=======

php-mf2 is a generic [microformats-2](http://microformats.org/wiki/microformats-2) parser. It doesn’t have a hard-coded list of all the different microformats, just a set of procedures to handle different property types (e.g. `p-` for plaintext, `u-` for URL, etc). This allows for a very small and maintainable parser.

## Installation

Install with [Composer](http://getcomposer.org) by adding `"mf2/mf2": "*"` to the `require` object in your `composer.json` and running <kbd>php composer.phar update</kbd>.

php-mf2 is not yet versioned, so you’ll have to either specify `"minimum-stability": "dev"` or `"mf2/mf2": "dev-master"`.

## Usage

mf2 is PRS-0 autoloadable, so all you have to do to load it is:

1. Include Composer’s auto-generated autoload file (`/vendor/autoload.php`)
1. Declare `mf2\Parser` in your `use` statement
1. Make a `new Parser($input)` where `$input` can either be a string of HTML or a DOMDocument

### Example Code

```
<?php

include $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use mf2\Parser;

$parser = new Parser('<div class="h-card"><p class="p-name">Barnaby Walters</p></div>');
$output = $parser -> parse();

print_r($output);

// EOF
```

This should result in the following output:

```
Array
(
    [h-card] => Array
        (
            [0] => Array
                (
                    [p-name] => Array
                        (
                            [0] => Barnaby Walters
                        )

                )

        )

)
```

A baseurl can be provided as the second parameter of `mf2\Parser::__construct()` — it’s prepended to any `u-` properties which are relative URLs.

## Output

mf2\Parser::parse() returns an associative array. The output pattern at any level (µf or property) is (expressed as JSON):

```
{
	"property-name": [
		"first instance of property-name",
		"second instance of property-name",
		•••
	]
}
```

### Output Types

Different µf-2 property types are returned as different types.

* `h-\*` are associative arrays containing more properties
* `p-\*` and `u-` are returned as whitespace-trimmed strings
* `dt-\*` are returned as \DateTime objects
* `e-\*` are returned as **non HTML encoded** strings of markup representing the `innerHTML` of the element classed as `e-\*`

### Security

**Little to no filtering of content takes place in mf2\Parser, so treat it’s output as you would any untrusted data from the source of the parsed document**

## Parsing Behaviour

TODO: Write up as prose

* Follows [µf2 prefix parsing guidelines](http://microformats.org/wiki/microformats-2-prefixes)
* At least an approximate implementation of the [Value-Class Pattern](http://microformats.org/wiki/value-class-pattern) on dt-\* **properties only**
* When a DOMElement with a classname of e-\* is found, the DOMNode::C14N() stringvalue of each of it’s children are concatenated and returned
* Doesn’t yet handle minimal h-cards (e.g. `<a class="h-card" href="http://waterpigs.co.uk">Barnaby Walters</a>`), TODO
* Lots of false positives are possible due to the generic parsing structure, put code in place to filter these out (they will usually be empty)