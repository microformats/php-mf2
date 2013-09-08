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

    $this->assertEquals($actual, $expected, $assert);
  }
	
	public function testData() {
		// seriously, please update to PHP 5.4 so I can use nice array syntax ;)
		// fail message, base, url, expected
		return array(
			array('Should return absolute URL unchanged',
				'http://example.com', 'http://example.com', 'http://example.com'),
			array('Should handle blank base URL',
				'', 'http://example.com', 'http://example.com'),
			array('Should resolve fragment ID',
				'http://example.com', '#thing', 'http://example.com#thing'),
			array('Should resolve blank fragment ID',
				'http://example.com', '#', 'http://example.com#'),
			array('Should resolve same level URL',
				'http://example.com', 'thing', 'http://example.com/thing'),
			array('Should resolve directory level URL',
				'http://example.com', './thing', 'http://example.com/thing'),
			array('Should resolve parent level URL at root level',
				'http://example.com', '../thing', 'http://example.com/thing'),
			array('Should resolve nested URL',
				'http://example.com/something', 'another', 'http://example.com/something/another'),
			array('Should respect query strings',
				'http://example.com/index.php?url=http://example.org', '/thing', 'http://example.com/thing'),
			array('Should resolve query strings',
				'http://example.com/thing', '?stuff=yes', 'http://example.com/thing?stuff=yes'),
			array('Should resolve dir level query strings',
				'http://example.com', './?thing=yes', 'http://example.com/?thing=yes')
		);
	}
}
