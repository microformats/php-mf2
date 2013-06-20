<?php

/**
 * Tests of the parsing methods within mf2\Parser
 */

namespace mf2\Parser\test;

// Include Parser.php
$autoloader = require_once dirname(__DIR__) . '/../mf2/Parser.php';

use mf2\Parser,
	PHPUnit_Framework_TestCase,
	DateTime;

/**
 * Parser Test
 * 
 * Contains tests for internal parsing functions and stuff which doesn’t go anywhere else, i.e.
 * isn’t related to a particular property as such.
 * 
 * Stuff for parsing E goes in here until there is enough of it to go elsewhere (like, never?)
 */
class ParserTest extends PHPUnit_Framework_TestCase {

	public function setUp() {
		date_default_timezone_set('Europe/London');
	}

	public function testMicroformatNameFromClassReturnsFullRootName() {
		$expected = array('h-card');
		$actual = Parser::mfNamesFromClass('someclass h-card someotherclass', 'h-');

		$this->assertEquals($actual, $expected);
	}

	public function testMicroformatNameFromClassHandlesMultipleHNames() {
		$expected = array('h-card', 'h-person');
		$actual = Parser::mfNamesFromClass('someclass h-card someotherclass h-person yetanotherclass', 'h-');

		$this->assertEquals($actual, $expected);
	}

	public function testMicroformatStripsPrefixFromPropertyClassname() {
		$expected = ['name'];
		$actual = Parser::mfNamesFromClass('someclass p-name someotherclass', 'p-');

		$this->assertEquals($actual, $expected);
	}

	public function testNestedMicroformatPropertyNameWorks() {
		$expected = ['location'];
		$test = 'someclass p-location someotherclass';
		$actual = Parser::nestedMfPropertyNamesFromClass($test);
		
		$this->assertEquals($actual, $expected);
	}
	
	/**
	 * @group parseE
	 */
	public function testParseE() {
		$input = '<div class="h-entry"><div class="e-content">Here is a load of <strong>embedded markup</strong></div></div>';
		$parser = new Parser($input);
		$output = $parser->parse();


		$this->assertArrayHasKey('content', $output['items'][0]['properties']);
		$this->assertEquals('Here is a load of <strong>embedded markup</strong>', $output['items'][0]['properties']['content'][0]);
	}

	/**
	 * @group parseH
	 */
	public function testInvalidClassnamesContainingHAreIgnored() {
		$input = '<div class="asdfgh-jkl"></div>';
		$parser = new Parser($input);
		$output = $parser->parse();

		// Look through $output for an item which indicate failure
		foreach ($output['items'] as $item) {
			if (in_array('asdfgh-jkl', $item['type']))
				$this->fail();
		}
	}
	
	public function testHtmlSpecialCharactersWorks() {
		$this->assertEquals('&lt;&gt;', htmlspecialchars('<>'));
	}
	
	public function testHtmlEncodesNonEProperties() {
		$input = '<div class="h-card">
			<span class="p-name">&lt;p&gt;</span>
			<span class="dt-published">&lt;dt&gt;</span>
			<span class="u-url">&lt;u&gt;</span>
			</div>';
		
		$parser = new Parser($input);
		$output = $parser->parse(true);
		
		$this->assertEquals('&lt;p&gt;', $output['items'][0]['properties']['name'][0]);
		$this->assertEquals('&lt;dt&gt;', $output['items'][0]['properties']['published'][0]);
		$this->assertEquals('&lt;u&gt;', $output['items'][0]['properties']['url'][0]);
	}
	
	public function testHtmlEncodesImpliedProperties() {
		$input = '<a class="h-card" href="&lt;url&gt;"><img src="&lt;img&gt;" />&lt;name&gt;</a>';
		$parser = new Parser($input);
		
		$output = $parser->parse(true);
		
		$this->assertEquals('&lt;name&gt;', $output['items'][0]['properties']['name'][0]);
		$this->assertEquals('&lt;url&gt;', $output['items'][0]['properties']['url'][0]);
		$this->assertEquals('&lt;img&gt;', $output['items'][0]['properties']['photo'][0]);
	}
	
	public function testParsesRelValues() {
		$this->markTestSkipped();
	}
	
	public function testParsesRelAlternateValues() {
		$this->markTestSkipped();
	}

}