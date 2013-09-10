<?php

/**
 * Tests of the URL resolver within mf2\Parser
 */

namespace mf2\Parser\test;

use mf2\Parser,
  mf2,
  PHPUnit_Framework_TestCase,
  DateTime;

class URLTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    date_default_timezone_set('Europe/London');
  }

	/**
	 * @dataProvider testData
	 */
  public function testReturnsUrlIfAbsolute($assert, $base, $url, $expected) {
    $actual = mf2\resolveUrl($base, $url);

    $this->assertEquals($expected, $actual, $assert);
  }
	
	public function testData() {
		// seriously, please update to PHP 5.4 so I can use nice array syntax ;)
		// fail message, base, url, expected
		return array(
			array('Should return absolute URL unchanged',
				'http://example.com', 'http://example.com', 'http://example.com'),

			array('Should return root given blank path',
				'http://example.com', '', 'http://example.com/'),

			array('Should return input unchanged given full URL and blank path',
				'http://example.com/something', '', 'http://example.com/something'),

			array('Should handle blank base URL',
				'', 'http://example.com', 'http://example.com'),

			array('Should resolve fragment ID',
				'http://example.com', '#thing', 'http://example.com/#thing'),

			array('Should resolve blank fragment ID',
				'http://example.com', '#', 'http://example.com/#'),

			array('Should resolve same level URL',
				'http://example.com', 'thing', 'http://example.com/thing'),

			array('Should resolve directory level URL',
				'http://example.com', './thing', 'http://example.com/thing'),

			array('Should resolve parent level URL at root level',
				'http://example.com', '../thing', 'http://example.com/thing'),

			array('Should resolve nested URL',
				'http://example.com/something', 'another', 'http://example.com/another'),

			array('Should ignore query strings in base url',
				'http://example.com/index.php?url=http://example.org', '/thing', 'http://example.com/thing'),

			array('Should resolve query strings',
				'http://example.com/thing', '?stuff=yes', 'http://example.com/thing?stuff=yes'),

			array('Should resolve dir level query strings',
				'http://example.com', './?thing=yes', 'http://example.com/?thing=yes'),

			array('Should resolve up one level from root domain',
				'http://example.com', 'path/to/the/../file', 'http://example.com/path/to/file'),

			array('Should resolve up one level from base with path',
				'http://example.com/path/the', 'to/the/../file', 'http://example.com/path/to/file'),

			// Tests from webignition library

			array('relative add host from base',
				'http://www.example.com', 'server.php', 'http://www.example.com/server.php'),

			array('relative add scheme host user from base',
				'http://user:@www.example.com', 'server.php', 'http://user:@www.example.com/server.php'),

			array('relative add scheme host pass from base',
				'http://:pass@www.example.com', 'server.php', 'http://:pass@www.example.com/server.php'),

			array('relative add scheme host user pass from base',
				'http://user:pass@www.example.com', 'server.php', 'http://user:pass@www.example.com/server.php'),

			array('relative base has file path',
				'http://example.com/index.html', 'example.html', 'http://example.com/example.html'),

			array('input has absolute path',
				'http://www.example.com/pathOne/pathTwo/pathThree', '/server.php?param1=value1', 'http://www.example.com/server.php?param1=value1'),

			array('test absolute url with path',
				'http://www.example.com/', 'http://www.example.com/pathOne', 'http://www.example.com/pathOne'),

			array('testRelativePathIsTransformedIntoCorrectAbsoluteUrl',
				'http://www.example.com/pathOne/pathTwo/pathThree', 'server.php?param1=value1', 'http://www.example.com/pathOne/pathTwo/server.php?param1=value1'),

			array('testAbsolutePathHasDotDotDirecoryAndSourceHasFileName',
				'http://www.example.com/pathOne/index.php', '../jquery.js', 'http://www.example.com/jquery.js'),

			array('testAbsolutePathHasDotDotDirecoryAndSourceHasDirectoryWithTrailingSlash',
				'http://www.example.com/pathOne/', '../jquery.js', 'http://www.example.com/jquery.js'),

			array('testAbsolutePathHasDotDotDirecoryAndSourceHasDirectoryWithoutTrailingSlash',
				'http://www.example.com/pathOne', '../jquery.js', 'http://www.example.com/jquery.js'),

			array('testAbsolutePathHasDotDirecoryAndSourceHasFilename',
				'http://www.example.com/pathOne/index.php', './jquery.js', 'http://www.example.com/pathOne/jquery.js'),

			array('testAbsolutePathHasDotDirecoryAndSourceHasDirectoryWithTrailingSlash',
				'http://www.example.com/pathOne/', './jquery.js', 'http://www.example.com/pathOne/jquery.js'),

			array('testAbsolutePathHasDotDirecoryAndSourceHasDirectoryWithoutTrailingSlash',
				'http://www.example.com/pathOne', './jquery.js', 'http://www.example.com/jquery.js')

		);
	}
}
