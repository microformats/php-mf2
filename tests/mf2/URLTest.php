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

  public function testResolveURLAbsolute() {
    $expected = 'http://example.com/';
    $actual = mf2\resolveUrl('http://example.com/', 'http://example.com/');

    $this->assertEquals($actual, $expected);
  }
}
