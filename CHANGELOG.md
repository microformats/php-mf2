# Changelog

## [Unreleased]

## [0.5.0] – 2022-02-10

**Breaking changes**:

* Bumped minimum PHP version from 5.4 to 5.6 ([#220](https://github.com/microformats/php-mf2/issues/220))
* [#214](https://github.com/microformats/php-mf2/issues/214) parse an img element for src and alt — i.e. all property values parsed as image URLs where the img element has an `alt` attribute will now be a `{'value': 'url', 'alt': 'the alt value'}` structure rather than a single URL string
* Renamed `master` branch to `main`. Anyone who had been installing the latest development version with `dev-master` will need to change their requirements to `dev-main`

Other changes:

* [#195](https://github.com/microformats/php-mf2/issues/195) Fix backcompat parsing for geo property
* [#182](https://github.com/microformats/php-mf2/issues/182) Fix parsing for iframe.`u-*[src]`
* [#206](https://github.com/microformats/php-mf2/issues/206) Add optional ID for `h-*` elements
* [#198](https://github.com/microformats/php-mf2/issues/198) reduce instances where photo is implied
* Internal: switched from Travis to Github Actions for CI

## [0.4.6] – 2018-08-24

Bugfixes:

* Don't include img src attribute in implied p-name ([#180](https://github.com/microformats/php-mf2/issues/180))
* Normalize ordinal dates in VCP values ([#167](https://github.com/microformats/php-mf2/issues/167))
* Fix for accidental array access of stdClass in deeply nested structures ([#196](https://github.com/microformats/php-mf2/issues/196))
* Reduce instances where `u-url` is implied according to a [spec update](http://microformats.org/wiki/index.php?title=microformats2-parsing&diff=66887&oldid=66871) ([#183](https://github.com/microformats/php-mf2/issues/183) and [parsing issue #36](https://github.com/microformats/microformats2-parsing/issues/36))
* Fix for wrongly implied photo property ([#190](https://github.com/microformats/php-mf2/issues/190))

Other Updates:

* Adds a filter to avoid running tests that require a live internet connection ([#194](https://github.com/microformats/php-mf2/pull/194))
* Refactor implied name code to match new implied name handling of photo and url ([#193](https://github.com/microformats/php-mf2/pull/193))
* Moved this repo to the microformats GitHub organization ([#179](https://github.com/microformats/php-mf2/issues/179))


## [0.4.5] – 2018-08-02

Bugfixes:

* Fix for parsing empty `e-` elements

Other Updates:

* Added `.editorconfig` to the project and cleaned up whitespace across all files


## [0.4.4] – 2018-08-01

Bugfixes:

* Ensure empty `properties` is an object `{}` rather than array  `[]` ([#171](https://github.com/microformats/php-mf2/issues/171))
* Ensure the parser does not mutate the `DOMDocument` passed in ([#174](https://github.com/microformats/php-mf2/issues/174))
* Fix for multiple class names in backcompat parsing ([#156](https://github.com/microformats/php-mf2/issues/156))

Microformats Parsing Updates:

* New algorithm for plaintext values ([#168](https://github.com/microformats/php-mf2/pull/168) and [parsing issue #15](https://github.com/microformats/microformats2-parsing/issues/15))
* Always resolve URLs from `u-` properties even when not from a link element ([Parsing issue #10](https://github.com/microformats/microformats2-parsing/issues/10))

Other Updates:

* Improved test coverage


## [0.4.3] – 2018-03-29

If the [masterminds/html5](https://github.com/Masterminds/html5-php) HTML5 parser is available, the Mf2 parser will use that instead of the built-in HTML parser. This enables proper handling of HTML5 elements such as `<article>`.

To include the HTML5 parser in your project, run:

```
composer require masterminds/html5
```

## [0.4.2] – 2018-03-29

Fixes:

* [#165](https://github.com/microformats/php-mf2/pull/165) - Prevents inadvertently adding whitespace to the html value
* [#158](https://github.com/microformats/php-mf2/issues/158) - Allows numbers in vendor prefixed names
* [#160](https://github.com/microformats/php-mf2/issues/160) - Ignores class names with consecutive dashes
* [#159](https://github.com/microformats/php-mf2/issues/159) - Remove duplicate values from type and rels arrays
* [#162](https://github.com/microformats/php-mf2/pull/162) - Improved rel attribute parsing

Backcompat:

* [#157](https://github.com/microformats/php-mf2/issues/157) - Parse `rel=tag` as `p-category` for hEntry and hReview

## [0.4.1] – 2018-03-15

Fixes:

* [#153](https://github.com/microformats/php-mf2/issues/153) - Fixes parsed timestamps authored with a Z timezone offset
* [#151](https://github.com/microformats/php-mf2/issues/151) - Adds back "value" of nested microformats when no matching property exists


## [0.4.0] – 2018-03-13

Breaking changes:

* [#125](https://github.com/microformats/php-mf2/pull/125) - Add `rel-urls` to parsed result. Removes `alternates` by default but still available behind a feature flag.
* [#142](https://github.com/microformats/php-mf2/pull/142) - Reduce instances of implied `p-name`. See Microformats issue [#6](https://github.com/microformats/microformats2-parsing/issues/6). This means it is now possible for the parsed result to *not* have a `name` property, whereas before there was always a `name` property on an object. Make sure consuming code can handle an object without a name now.

Fixes:

* [#124](https://github.com/microformats/php-mf2/pull/124) - Fix for experimental lang parsing
* [#127](https://github.com/microformats/php-mf2/issues/127) - Fix for parsing `h-*` class names containing invalid characters.
* [#131](https://github.com/microformats/php-mf2/pull/131) - Improved `dt-` parsing. Issues [#126](https://github.com/microformats/php-mf2/issues/126) and [#115](https://github.com/microformats/php-mf2/issues/115).
* [#130](https://github.com/microformats/php-mf2/issues/130) - Fix for implied properties with empty attributes.
* [#135](https://github.com/microformats/php-mf2/issues/135) - Trim leading and tailing whitespace from HTML value as well as text value.
* [#137](https://github.com/microformats/php-mf2/issues/137) - Fix backcompat hfeed parsing.
* [#134](https://github.com/microformats/php-mf2/issues/134) - Fix `rel=bookmark` backcompat parsing.
* [#116](https://github.com/microformats/php-mf2/issues/116) - Fix backcompat parsing for `summary` property in `hreview`
* [#149](https://github.com/microformats/php-mf2/issues/149) - Fix for datetime parsing, no longer tries to interpret the value and passes through instead

## [0.3.2] – 2017-05-27

* Fixed how the Microformats tests repo is loaded via composer
* Moved experimental language parsing feature behind an opt-in flag
* [#121](https://github.com/microformats/php-mf2/pull/121) Fixed language detection to support parsing of HTML fragments

## [0.3.1] – 2017-05-25

2017-05-24

* [#89](https://github.com/microformats/php-mf2/issues/89) - Fixed parsing empty `img alt=""` attributes
* [#91](https://github.com/microformats/php-mf2/issues/91) - Ignore rel values from HTML tags that don't allow rel values
* [#57](https://github.com/microformats/php-mf2/issues/57) - Implement hAtom rel=bookmark backcompat
* [#94](https://github.com/microformats/php-mf2/pull/94) - Fixed HTML output when parsing `e-*` properties
* [#97](https://github.com/microformats/php-mf2/pull/97) - Experimental language parsing
* [#88](https://github.com/microformats/php-mf2/issues/88) - Fix for implied photo parsing
* [#102](https://github.com/microformats/php-mf2/pull/102) - Ignore classes with numbers or capital letters
* [#111](https://github.com/microformats/php-mf2/pull/111) - Improved backcompat parsing
* [#106](https://github.com/microformats/php-mf2/issues/106) - Send `Accept: text/html` header when using the `fetch` method
* [#114](https://github.com/microformats/php-mf2/issues/114) - Parse `poster` attribute for `video` tags
* [#118](https://github.com/microformats/php-mf2/issues/118) - Fixes parsing elements with missing attributes
* Tests now use [microformats/tests](https://github.com/microformats/tests) repo

Many thanks to @gRegorLove for the major overhaul of the backcompat parsing!

## [0.3.0] – 2016-03-14

* Requires PHP 5.4 at minimum (PHP 5.3 is EOL)
* Licensed under CC0 rather than MIT
* Merges Pull requests #70, #73, #74, #75, #77, #80, #82, #83, #85 and #86.
* Variety of small bug fixes and features including improved whitespace support, removal of style and script contents from plaintext properties
* All PHPUnit tests passing finally

Many thanks to @aaronpk, @diplix, @dissolve, @dymcx @gRegorLove, @jeena, @veganstraightedge and @voxpelli for all your hard work opening issues and sending and merging PRs!

## [0.2.12] – 2015-07-12

* Merges pull requests [#65](https://github.com/microformats/php-mf2/pull/65), [#66](https://github.com/microformats/php-mf2/pull/66) and [#67](https://github.com/microformats/php-mf2/pull/67).
* Fixes issue [#64](https://github.com/microformats/php-mf2/issues/64).

Many thanks to @aaronpk, @gRegorLove and @kylewm for contributions, @aaronpk and @kevinmarks for PR management and @tantek for issue reporting!

## [0.2.11] – 2015-07-10

## [0.2.10] – 2015-04-29

* Merged [#58](https://github.com/microformats/php-mf2/pull/58), fixing some parsing bugs and adding support for area element parsing. Thanks so much for your hard work and patience, <a class="h-card" href="http://ben.thatmustbe.me/">Ben</a>!

## [0.2.9] – 2014-08-06

* Added backcompat classmap for hProduct, associated tests
* Started GPG signing version tags as barnaby@waterpigs.co.uk, fingerprint `CBC7 7876 BF7C 9637 B6AE 77BA 7D49 834B 0416 CFA3`

## [0.2.8] – 2014-07-17

* Fixed issue #51 causing php-mf2 to not work with PHP 5.3
* Fixed issue #52 correctly handling the `<template>` element by ignoring it
* Fixed issue #53 improving the plaintext parsing of `<img>` elements

## [0.2.7] – 2014-06-18

* Added `Mf2\fetch()` which fetches content from a URL and returns parsed microformats
* Added implied `dt-end` discovery (thanks for all your hard work, @gRegorLove!)
* Fixed issue causing classnames like `blah e- blah` to produce properties with numeric keys (thanks @aaronpk and @gRegorLove)
* Fixed issue causing resolved URLs to not include port numbers (thanks @aaronpk)

## [0.2.6] – 2014-04-07

* Added JSON mode as long-term fix for #29
* Fixed bug causing microformats nested under multiple property names to be parsed only once

## [0.2.5] – 2014-02-24

* Removed conditional replacing empty rel list with stdclass. Original purpose was to make JSON-encoding the output from the parser correct but it also caused Fatal Errors due to trying to treat stdclass as array.

## [0.2.4] – 2013-11-27

## [0.2.3] – 2013-11-12

* Made `p-*` parsing consistent with implied name parsing
* Stopped collapsing whitespace in `p-*` properties
* Implemented `unicodeTrim` which removes `&nbsp;` characters as well as regex `\s`
* Added support for implied name via `abbr[title]`
* Prevented excessively nested value-class elements from being parsed incorrectly, removed incorrect separator which was getting added in some cases
* Updated `u-*` parsing to be spec-compliant, matching `[href]` before value-class and only attempting URL resolution for URL attributes
* Added support for `input[value]` parsing
* Tests for all the above

## [0.2.2] – 2013-10-30

* Made resolveUrl method public, allowing advanced parsers and subclasses to make use of it
* Fixed bug causing multiple duplicate property values to appear

## [0.2.1] – 2013-10-29

* Fixed bug causing classic microformats property classnames to not be parsed correctly

## [0.2.0] (BREAKING CHANGES) – 2013-10-20

* Namespace change from `mf2` to `Mf2`, for PSR-0 compatibility
* `Mf2\parse()` function added to simplify the most common case of just parsing some HTML
* Updated `e-*` property parsing rules to match mf2 parsing spec — instead of producing inconsistent HTML content, it now produces dictionaries like <pre><code>
{
	"html": "<b>The Content</b>",
	"value: "The Content"
}
</code></pre>
* Removed `htmlSafe` options as new `e-*` parsing rules make them redundant
* Moved a whole load of static functions out of the class and into standalone functions
* Changed autoloading to always include Parser.php instead of using classmap

## [0.1.23] – 2013-10-20

* Made some changes to the way back-compatibility with classic microformats are handled, ignoring classic property classnames inside mf2 roots and outside classic roots
* Deprecated ability to add new classmaps, removed twitter classmap. Use [php-mf2-shim](http://github.com/microformats/php-mf2-shim) instead, it’s better

## [0.1.22] – 2013-10-17

* Converts classic microformats by default

## [0.1.21] – 2013-09-12

* Removed webignition dependency, also removing ext-intl dependency. php-mf2 is now a standalone, single file library again
* Replaced webignition URL resolving with custom code passing almost all tests, courtesy of <a class="h-card" href="http://aaronparecki.com">Aaron Parecki</a>

## [0.1.20] – 2013-09-12

* Added in almost-perfect custom URL resolving code

## [0.1.19] – 2013-07-11

* Required stable version of webigniton/absolute-url-resolver, hopefully resolving versioning problems

## [0.1.18] – 2013-07-05

* Fixed problems with isElementParsed, causing elements to be incorrectly parsed
* Cleaned up some test files

## [0.1.17] – 2013-06-24

* Rewrote some PHP 5.4 array syntax which crept into 0.1.16 so php-mf2 still works on PHP 5.3
* Fixed a bug causing weird partial microformats to be added to parent microformats if they had doubly property-nested children
* Finally actually licensed this project under a real license (MIT, in composer.json)
* Suggested barnabywalters/mf-cleaner in composer.json

## [0.1.16] – 2013-06-23

* Ability to parse from only an ID
* Context DOMElement can be passed to `$parse`
* `Parser::query` runs XPath queries on the current document
* When parsing `e-*` properties, elements with `@src`, `@data` or `@href` have relative URLs resolved in the output

## [0.1.15] – 2013-06-22

* Added html-safe options
* Added rel+rel-alternate parsing


[Unreleased]: https://github.com/microformats/php-mf2/compare/v0.5.0...HEAD
[0.5.0]: https://github.com/microformats/php-mf2/compare/0.4.6...v0.5.0
[0.4.6]: https://github.com/microformats/php-mf2/compare/v0.4.5...0.4.6
[0.4.5]: https://github.com/microformats/php-mf2/compare/v0.4.4...v0.4.5
[0.4.4]: https://github.com/microformats/php-mf2/compare/v0.4.3...v0.4.4
[0.4.3]: https://github.com/microformats/php-mf2/compare/v0.4.2...v0.4.3
[0.4.2]: https://github.com/microformats/php-mf2/compare/v0.4.1...v0.4.2
[0.4.1]: https://github.com/microformats/php-mf2/compare/v0.4.0...v0.4.1
[0.4.0]: https://github.com/microformats/php-mf2/compare/v0.3.2...v0.4.0
[0.3.2]: https://github.com/microformats/php-mf2/compare/v0.3.1...v0.3.2
[0.3.1]: https://github.com/microformats/php-mf2/compare/v0.3.0...v0.3.1
[0.3.0]: https://github.com/microformats/php-mf2/compare/v0.2.12...v0.3.0
[0.2.12]: https://github.com/microformats/php-mf2/compare/v0.2.11...v0.2.12
[0.2.11]: https://github.com/microformats/php-mf2/compare/v0.2.10...v0.2.11
[0.2.10]: https://github.com/microformats/php-mf2/compare/v0.2.9...v0.2.10
[0.2.9]: https://github.com/microformats/php-mf2/compare/v0.2.8...v0.2.9
[0.2.8]: https://github.com/microformats/php-mf2/compare/0.2.7...v0.2.8
[0.2.7]: https://github.com/microformats/php-mf2/compare/v0.2.6...0.2.7
[0.2.6]: https://github.com/microformats/php-mf2/compare/v0.2.5...v0.2.6
[0.2.5]: https://github.com/microformats/php-mf2/compare/v0.2.4...v0.2.5
[0.2.4]: https://github.com/microformats/php-mf2/compare/v0.2.3...v0.2.4
[0.2.3]: https://github.com/microformats/php-mf2/compare/v0.2.2...v0.2.3
[0.2.2]: https://github.com/microformats/php-mf2/compare/v0.2.1...v0.2.2
[0.2.1]: https://github.com/microformats/php-mf2/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/microformats/php-mf2/compare/v0.1.23...v0.2.0
[0.1.23]: https://github.com/microformats/php-mf2/compare/v0.1.22...v0.1.23
[0.1.22]: https://github.com/microformats/php-mf2/compare/v0.1.21...v0.1.22
[0.1.21]: https://github.com/microformats/php-mf2/compare/v0.1.20...v0.1.21
[0.1.20]: https://github.com/microformats/php-mf2/compare/v0.1.19...v0.1.20
[0.1.19]: https://github.com/microformats/php-mf2/compare/v0.1.18...v0.1.19
[0.1.18]: https://github.com/microformats/php-mf2/compare/v0.1.17...v0.1.18
[0.1.17]: https://github.com/microformats/php-mf2/compare/v0.1.16...v0.1.17
[0.1.16]: https://github.com/microformats/php-mf2/compare/v0.1.15...v0.1.16
