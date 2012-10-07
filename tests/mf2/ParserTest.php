<?php
/**
 * Tests of the parsing methods within mf2\Parser 
 */

namespace mf2\Parser\test;

// Include Parser.php
$autoloader = require_once dirname(__DIR__) . '/../mf2/Parser.php';

use mf2\Parser,
	PHPUnit_Framework_TestCase;

class ParserTest extends PHPUnit_Framework_TestCase
{	
	/**
	 * @group parseP
	 */
	public function testParsePHandlesInnerText()
	{
		$input = '<div class="h-card"><p class="p-name">Example User</p></div>';
		$parser = new Parser($input);
		$output = $parser -> parse();
		
		$this -> assertArrayHasKey('h-card', $output);
		$this -> assertArrayHasKey('p-name', $output['h-card'][0]);
		$this -> assertEquals('Example User', $output['h-card'][0]['p-name'][0]);
	}
	
	/**
	 * @group parseP
	 */
	public function testParsePHandlesImg()
	{
		$input = '<div class="h-card"><img class="p-name" alt="Example User"></div>';
		$parser = new Parser($input);
		$output = $parser -> parse();
		
		$this -> assertArrayHasKey('h-card', $output);
		$this -> assertArrayHasKey('p-name', $output['h-card'][0]);
		$this -> assertEquals('Example User', $output['h-card'][0]['p-name'][0]);
	}
	
	/**
	 * @group parseP
	 */
	public function testParsePHandlesAbbr()
	{
		$input = '<div class="h-card"><abbr class="p-name" title="Example User">@example</abbr></div>';
		$parser = new Parser($input);
		$output = $parser -> parse();
		
		$this -> assertArrayHasKey('h-card', $output);
		$this -> assertArrayHasKey('p-name', $output['h-card'][0]);
		$this -> assertEquals('Example User', $output['h-card'][0]['p-name'][0]);
	}
	
	/**
	 * @group parseU
	 */
	public function testParseUHandlesA()
	{
		$input = '<div class="h-card"><a class="u-url" href="http://example.com">Awesome example website</a></div>';
		$parser = new Parser($input);
		$output = $parser -> parse();
		
		$this -> assertArrayHasKey('h-card', $output);
		$this -> assertArrayHasKey('u-url', $output['h-card'][0]);
		$this -> assertEquals('http://example.com', $output['h-card'][0]['u-url'][0]);
	}
	
	/**
	 * @group parseU
	 */
	public function testParseUHandlesImg()
	{
		$input = '<div class="h-card"><img class="u-photo" src="http://example.com/someimage.png"></div>';
		$parser = new Parser($input);
		$output = $parser -> parse();
		
		$this -> assertArrayHasKey('h-card', $output);
		$this -> assertArrayHasKey('u-photo', $output['h-card'][0]);
		$this -> assertEquals('http://example.com/someimage.png', $output['h-card'][0]['u-photo'][0]);
	}
}

// EOF tests/mf2/testParser.php