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
class ParserTest extends PHPUnit_Framework_TestCase
{	
	public function setUp()
	{
		date_default_timezone_set('Europe/London');
	}
	
	public function testMicroformatNameFromClassReturnsFullRootName()
	{
		$expected = array('h-card');
		$actual = Parser::mfNameFromClass('someclass h-card someotherclass', 'h-');
		
		$this -> assertEquals($actual, $expected);
	}
	
	public function testMicroformatNameFromClassHandlesMultipleHNames()
	{
		$expected = array('h-card', 'h-person');
		$actual = Parser::mfNameFromClass('someclass h-card someotherclass h-person yetanotherclass', 'h-');
		
		$this -> assertEquals($actual, $expected);
	}
	
	public function testMicroformatStripsPrefixFromPropertyClassname()
	{
		$expected = 'name';
		$actual = Parser::mfNameFromClass('someclass p-name someotherclass', 'p-');
		
		$this -> assertEquals($actual, $expected);
	}
	
	/**
	 * @group parseE
	 */
	public function testParseE()
	{
		$input = '<div class="h-entry"><div class="e-content">Here is a load of <strong>embedded markup</strong></div></div>';
		$parser = new Parser($input);
		$output = $parser -> parse();
		
		
		$this -> assertArrayHasKey('content', $output['items'][0]['properties']);
		$this -> assertEquals('Here is a load of <strong>embedded markup</strong>', $output['items'][0]['properties']['content'][0]);
	}
	
	/**
	 * @group parseH
	 */
	public function testInvalidClassnamesContainingHAreIgnored()
	{
		$input = '<div class="asdfgh-jkl"></div>';
		$parser = new Parser($input);
		$output = $parser -> parse();
		
		// Look through $output for an item which indicate failure
		foreach ($output['items'] as $item)
		{
			if (in_array('asdfgh-jkl', $item['type']))
				$this -> fail();
		}
	}
}

// EOF tests/mf2/testParser.php