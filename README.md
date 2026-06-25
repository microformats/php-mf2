# php-mf2

[![Latest Stable Version](http://poser.pugx.org/mf2/mf2/v)](https://packagist.org/packages/mf2/mf2) [![Total Downloads](http://poser.pugx.org/mf2/mf2/downloads)](https://packagist.org/packages/mf2/mf2) [![Latest Unstable Version](http://poser.pugx.org/mf2/mf2/v/unstable)](https://packagist.org/packages/mf2/mf2) [![License](http://poser.pugx.org/mf2/mf2/license)](https://packagist.org/packages/mf2/mf2) [![PHP Version Require](http://poser.pugx.org/mf2/mf2/require/php)](https://packagist.org/packages/mf2/mf2) <a href="https://github.com/microformats/php-mf2/actions/workflows/main.yml"><img src="https://github.com/microformats/php-mf2/actions/workflows/main.yml/badge.svg?branch=main" alt="" /></a>

php-mf2 is a pure, generic [microformats-2](http://microformats.org/wiki/microformats-2) parser. It makes HTML as easy to consume as JSON.

Instead of having a hard-coded list of all the different microformats, it follows a set of procedures to handle different property types (e.g. `p-` for plaintext, `u-` for URL, etc). This allows for a very small and maintainable parser.

## Installation

[!NOTE]
> This will be the last release that supports PHP 5.6 as a minimum version. The next release, 0.6.0, will require PHP 7.4 as a minimum.

There are two ways of installing php-mf2. We **highly recommend** installing php-mf2 using [Composer](http://getcomposer.org). The rest of the documentation assumes that you have done so.

To install using Composer, run

```
composer require mf2/mf2
```

If you can’t or don’t want to use Composer, then php-mf2 can be installed the old way by downloading [`/Mf2/Parser.php`](https://raw.githubusercontent.com/microformats/php-mf2/master/Mf2/Parser.php), adding it to your project and requiring it from files you want to call its functions from, like this:

```php
<?php

require_once 'Mf2/Parser.php';

// Now all the functions documented below are available, for example:
$mf = Mf2\fetch('https://waterpigs.co.uk');
```

It is recommended to install the HTML5 parser for proper handling of HTML5 elements. Using composer, run

```
composer require masterminds/html5
```

If this library is added to your project, the php-mf2 parser will use it automatically instead of the built-in HTML parser.


### Signed Code Verification

From v0.2.9, php-mf2’s version tags are signed using GPG, allowing you to cryptographically verify that you’re using code which hasn’t been tampered with. To verify the code you will need the GPG keys for one of the people in the list of code signers:

* Barnaby Walters barnaby@waterpigs.co.uk 1C00 430B 19C6 B426 922F E534 BEF8 CE58 118A D524
* Aaron Parecki aaron@parecki.com F384 12A1 55FB 8B15 B7DD 8E07 4225 2B5E 65CE 0ADD
* Bear bear@bear.im 0A93 9BA7 8203 FCBC 58A9 E8B5 9D1E 0661 8EE5 B4D8

To import the relevant keys into your GPG keychain, execute the following command:

```bash
gpg --recv-keys 1C00430B19C6B426922FE534BEF8CE58118AD524 F38412A155FB8B15B7DD8E0742252B5E65CE0ADD 0A939BA78203FCBC58A9E8B59D1E06618EE5B4D8
```

Then verify the installed files like this:

```bash
# in your project root
cd vendor/mf2/mf2
git tag -v v0.3.0
```

If nothing went wrong, you should see the tag commit message, ending something like this:

```
gpg: Signature made Wed  6 Aug 10:04:20 2014 GMT using RSA key ID 2B2BBB65
gpg: Good signature from "Barnaby Walters <barnaby@waterpigs.co.uk>"
gpg:                 aka "[jpeg image of size 12805]"
```

Possible issues:

* **Git complains that there’s no such tag**: check for a .git file in the source folder; odds are you have the prefer-dist setting enabled and composer is just extracting a zip rather than checking out from git.
* **Git complains the gpg command doesn’t exist**: If you successfully imported my key then you obviously do have gpg installed, but you might have gpg2, whereas git looks for gpg. Solution: tell git which binary to use: `git config --global gpg.program 'gpg2'`

## Usage

php-mf2 is PSR-0 autoloadable, so simply include Composer’s auto-generated autoload file (`/vendor/autoload.php`) and you can start using it. These two functions cover most situations:

* To fetch microformats from a URL, call `Mf2\fetch($url)`
* To parse microformats from HTML, call `Mf2\parse($html, $url)`, where `$url` is the URL from which `$html` was loaded, if any. This parameter is required for correct relative URL parsing and must not be left out unless parsing HTML which is not loaded from the web.

All parsing functions return a canonical microformats 2 representation of any microformats found on the page, as an array. For a general guide to safely and successfully processing parsed microformats data, see [How to Consume Microformats 2 Data](https://waterpigs.co.uk/articles/consuming-microformats/).

## Examples

### Fetching Microformats from a URL

```php
<?php

namespace YourApp;

require '/vendor/autoload.php';

use Mf2;

// (Above code (or equivalent) assumed in future examples)

$mf = Mf2\fetch('http://microformats.org');

// $mf is either a canonical mf2 array, or null on an error.
if (is_array($mf)) {
  foreach ($mf['items'] as $microformat) {
    // Note: in real code, never assume that a property exists, or that a particular property value is a string!
    echo "A {$microformat['type'][0]} called {$microformat['properties']['name'][0]}\n";
  }
}

```

### Parsing Microformats from a HTML String

Here we demonstrate parsing of microformats2 implied property parsing, where an entire h-card with name and URL properties is created using a single `h-card` class.

```php
<?php

$html = '<a class="h-card" href="https://waterpigs.co.uk/">Barnaby Walters</a>';
$output = Mf2\parse($html, 'https://waterpigs.co.uk/');
```

`$output` is a canonical microformats2 array structure like:

```json
{
  "items": [
    {
      "type": ["h-card"],
      "properties": {
        "name": ["Barnaby Walters"],
        "url": ["https://waterpigs.co.uk/"]
      }
    }
  ],
  "rels": {},
  "rel-urls": {}
}
```

If no microformats are found, `items` will be an empty array.

Note that, whilst the property prefixes are stripped, the prefix of the `h-*` classname(s) in the "type" array are retained.

### Parsing a Document with Relative URLs

Most of the time you’ll be getting your input HTML from a URL. You should pass that URL as the second parameter to `Mf2\parse()` so that any relative URLs in the document can be resolved. For example, say you got the following HTML from `http://example.org/post/1`:

```html
<div class="h-card">
  <h1 class="p-name">Mr. Example</h1>
  <img class="u-photo" alt="" src="/photo.png" />
</div>
```

Parsing like this:

```php
$output = Mf2\parse($html, 'http://example.org/post/1');
```

will result in the following output, with relative URLs made absolute:

```json
{
  "items": [{
    "type": ["h-card"],
    "properties": {
      "name": ["Mr. Example"],
      "photo": [{
        "value": "http://example.org/photo.png",
        "alt": ""
      }]
    }
  }],
  "rels": {},
  "rel-urls": {}
}
```

php-mf2 correctly handles relative URL resolution according to the URI and HTML specs, including correct use of the `<base>` element.

### Parsing Link `rel` Values

php-mf2 also parses any link relations in the document, placing them into two top-level arrays. For convenience and completeness, one is indexed by each individual rel value, and the other by each URL.

For example, this HTML:

```html
<a rel="me" href="https://twitter.com/barnabywalters">Me on twitter</a>
<link rel="alternate etc" href="http://example.com/notes.atom" />
```

parses to the following canonical representation:

```json
{
  "items": [],
  "rels": {
    "me": ["https://twitter.com/barnabywalters"],
    "alternate": ["http://example.com/notes.atom"],
    "etc": ["http://example.com/notes.atom"]
  },
  "rel-urls": {
    "https://twitter.com/barnabywalters": {
      "text": "Me on twitter",
      "rels": ["me"]
    },
    "http://example.com/notes.atom": {
      "rels": ["alternate", "etc"]
    }
  }
}
```

If you’re not bothered about the microformats2 data and just want rels and alternates, you can (very slightly) improve performance by creating a `Mf2\Parser` object (see below) and calling `->parseRelsAndAlternates()` instead of `->parse()`, e.g.

```php
<?php

$parser = new Mf2\Parser('<link rel="…');
$relsAndAlternates = $parser->parseRelsAndAlternates();
```

### Debugging Mf2\fetch

`Mf2\fetch()` will attempt to parse any response served with “HTML” in the content-type, regardless of what the status code is. If it receives a non-HTML response it will return null.

To learn what the HTTP status code for any request was, or learn more about the request, pass a variable name as the third parameter to `Mf2\fetch()` — this will be filled with the contents of `curl_getinfo()`, e.g:

```php

<?php

$mf = Mf2\fetch('http://waterpigs.co.uk/this-page-doesnt-exist', true, $curlInfo);
if ($curlInfo['http_code'] == '404') {
  // This page doesn’t exist.
}

```

If it was HTML then it is still parsed, as there are cases where error pages contain microformats — for example a deleted h-entry resulting in a 410 Gone response containing a stub h-entry with an explanation for the deletion.

### Getting more control by creating a Parser object

The `Mf2\parse()` function covers the most common usage patterns by internally creating an instance of `Mf2\Parser` and returning the output all in one step. For some advanced usage you can also create an instance of `Mf2\Parser` yourself.

The constructor takes two arguments, the input HTML (or a DOMDocument) and the URL to use as a base URL. Once you have a parser, there are a few other things you can do:

### Selectively Parsing a Document

There are several ways to selectively parse microformats from a document. If you wish to only parse microformats from an element with a particular ID, `Parser::parseFromId($id) ` is the easiest way.

If your needs are more complex, `Parser::parse` accepts an optional context DOMNode as its second parameter. Typically you’d use `Parser::query` to run XPath queries on the document to get the element you want to parse from under, then pass it to `Parser::parse`. Example usage:

```php
$doc = 'More microformats, more microformats <div id="parse-from-here"><span class="h-card">This shows up</span></div> yet more ignored content';
$parser = new Mf2\Parser($doc);

$parser->parseFromId('parse-from-here'); // returns a document with only the h-card descended from div#parse-from-here

$elementIWant = $parser->query('an xpath query')[0];

$parser->parse(true, $elementIWant); // returns a document with only the Microformats under the selected element

```

### Experimental Language Parsing

There is still [ongoing brainstorming](http://microformats.org/wiki/microformats2-parsing-brainstorming#Parse_language_information) around how HTML language attributes should be added to the parsed result. In order to use this feature, you will need to set a flag to opt in.

```php
$doc = '<div class="h-entry" lang="sv" id="postfrag123">
  <h1 class="p-name">En svensk titel</h1>
  <div class="e-content" lang="en">With an <em>english</em> summary</div>
  <div class="e-content">Och <em>svensk</em> huvudtext</div>
</div>';
$parser = new Mf2\Parser($doc);
$parser->lang = true;
$result = $parser->parse();
```

```json
{
  "items": [
    {
      "type": ["h-entry"],
      "properties": {
        "name": ["En svensk titel"],
        "content": [
          {
            "html": "With an <em>english</em> summary",
            "value": "With an english summary",
            "lang": "en"
          },
          {
            "html": "Och <em>svensk</em> huvudtext",
            "value": "Och svensk huvudtext",
            "lang": "sv"
          }
        ]
      },
      "lang": "sv"
    }
  ],
  "rels": {},
  "rel-urls": {}
}
```

Note that this option is still considered experimental and in development, and the parsed output may change between minor releases.

### Generating output for JSON serialization with JSON-mode

Due to a quirk with the way PHP arrays work, there is an edge case ([reported](https://github.com/microformats/php-mf2/issues/29) by Tom Morris) in which a document with no rel values, when serialised as JSON, results in an empty object as the rels value rather than an empty array. Replacing this in code with a stdClass breaks PHP iteration over the values.

As of version 0.2.6, the default behaviour is back to being PHP-friendly, so if you want to produce results specifically for serialisation as JSON (for example if you run a HTML -> JSON service, or want to run tests against JSON fixtures), enable JSON mode:

```php
// …by passing true as the third constructor:
$jsonParser = new Mf2\Parser($html, $url, true);
```

### Classic Microformats Markup

php-mf2 has some support for parsing classic microformats markup. It’s enabled by default, but can be turned off by calling `Mf2\parse($html, $url, false);` or `$parser->parse(false);` if you’re instantiating a parser yourself.

If the built in mappings don’t successfully parse some classic microformats markup, please raise an issue and we’ll fix it.

## Security

**No filtering of content takes place in mf2\Parser, so treat its output as you would any untrusted data from the source of the parsed document.**

Some tips:

* All content apart from the 'html' key in dictionaries produced by parsing an `e-*` property is not HTML-escaped. For example, `<span class="p-name">&lt;code&gt;</span>` will result in `"name": ["<code>"]`. At the very least, HTML-escape all properties before echoing them out in HTML
* If you’re using the raw HTML content under the 'html' key of dictionaries produced by parsing `e-*` properties, you SHOULD purify the HTML before displaying it to prevent injection of arbitrary code. For PHP we recommend using [HTML Purifier](http://htmlpurifier.org)

## Contributing

Issues and bug reports are very welcome. If you know how to write tests then please do so as code always expresses problems and intent much better than English, and gives me a way of measuring whether or not fixes have actually solved your problem. If you don’t know how to write tests, don’t worry :) Just include as much useful information in the issue as you can.

Pull requests very welcome, please try to maintain stylistic, structural and naming consistency with the existing codebase, and don’t be too upset if we make naming changes :)

### How to make a Pull Request

1. Fork the repo to your github account
2. Clone a copy to your computer (simply installing php-mf2 using composer only works for using it, not developing it)
3. Install the dev dependencies with `composer install`.
4. Run PHPUnit with `./vendor/bin/phpunit`
6. Add PHPUnit tests for your changes, either in an existing test file if suitable, or a new one
7. Make your changes
8. Make sure your tests pass (`./vendor/bin/phpunit`) and that your code is compatible with all supported versions of PHP (`./vendor/bin/phpcs -p`)
9. Go to your fork of the repo on github.com and make a pull request, preferably with a short summary, detailed description and references to issues/parsing specs as appropriate
10. Bask in the warm feeling of having contributed to a piece of free software (optional)

### Testing

There are currently two separate test suites: one, in `tests/Mf2`, is written in phpunit, containing many microformats parsing examples as well as internal parser tests and regression tests for specific issues over php-mf2’s history. Run it with `./vendor/bin/phpunit`. If you do not have a live internet connection, you can exclude tests that depend on it: `./vendor/bin/phpunit --exclude-group internet`.

The other, in `tests/test-suite`, is a custom test harness which hooks up php-mf2 to the cross-platform [microformats test suite](https://github.com/microformats/tests). To run these tests you must first install the tests with `./composer.phar install`. Each test consists of a HTML file and a corresponding JSON file, and the suite can be run with `php ./tests/test-suite/test-suite.php`.

Currently php-mf2 passes the majority of it’s own test case, and a good percentage of the cross-platform tests. Contributors should ALWAYS test against the PHPUnit suite to ensure any changes don’t negatively impact php-mf2, and SHOULD run the cross-platform suite, especially if you’re changing parsing behaviour.


## License

php-mf2 is dedicated to the public domain using Creative Commons -- CC0 1.0 Universal.

http://creativecommons.org/publicdomain/zero/1.0
