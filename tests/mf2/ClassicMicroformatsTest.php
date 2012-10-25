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
 * Classic Microformats Test
 * 
 * 
 */
class ParserTest extends PHPUnit_Framework_TestCase
{	
	public function setUp()
	{
		date_default_timezone_set('Europe/London');
	}
	
	public function testConvertClassic()
	{
		$input = '<div class="vcard">
 <a class="url fn" href="http://tantek.com/">Tantek Çelik</a>
</div>';
		$expected = '<div class="h-card">
 <a class="u-url p-name" href="http://tantek.com/">Tantek Çelik</a>
</div>';
		
		$parser = new Parser('');
		$converted = $parser->convertClassic($input);
		
		$this -> assertEquals($expected, $converted);
	}
}