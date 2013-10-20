<?php

/**
 * Tests of the parsing methods within mf2\Parser
 */

namespace mf2\Parser\test;

use mf2\Parser;
use PHPUnit_Framework_TestCase;
use DateTime;

/**
 * Classic Microformats Test
 * 
 * Contains tests of the classic microformat => µf2 functionality.
 * 
 * Mainly based off BC tables on http://microformats.org/wiki/microformats2#v2_vocabularies
 */
class ClassicMicroformatsTest extends PHPUnit_Framework_TestCase {
	public function setUp() {
		date_default_timezone_set('Europe/London');
	}
	
	public function testParsesClassicHcard() {
		$input = '<div class="vcard"><span class="fn n">Barnaby Walters</span></div>';
		$expected = '{"items": [{"type": ["h-card"], "properties": {"name": ["Barnaby Walters"]}}], "rels": {}}';
		$parser = new Parser($input);
		$this->assertJsonStringEqualsJsonString(json_encode($parser->parse()), $expected);
	}
	
	public function testIgnoresClassicClassnamesUnderMf2Root() {
		
	}
}