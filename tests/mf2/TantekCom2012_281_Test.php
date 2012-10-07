<?php
/**
 * Tests of mf2\Parser on real-world data from 2012-281
 */

namespace mf2\Parser\TantekCom\HTML2012_281;

// Include Parser.php
$autoloader = require_once dirname(__DIR__) . '/../mf2/Parser.php';

use mf2\Parser,
	PHPUnit_Framework_TestCase,
	DateTime;

class ParserTest extends PHPUnit_Framework_TestCase
{	
	private $html;
	
	public function setUp()
	{
		date_default_timezone_set('Europe/London');
		$this -> html = file_get_contents(dirname(__DIR__) . '/../mf2/html/tantekCom_2012-281.html');
		$this -> parser = new Parser($this -> html, 'http://tantek.com/');
	}
	
	/**
	 * @group parseDT
	 * @group valueClass
	 */
	public function testYYYY_MM_DD__HH_MM()
	{
		$this -> assertArrayHasKey('h-event', $output);
		$this -> assertArrayHasKey('dt-start', $output['h-event'][0]);
		$this -> assertEquals($datetime -> format(DateTime::ISO8601), $output['h-event'][0]['dt-start'][0] -> format(DateTime::ISO8601));
	}
}

// EOF