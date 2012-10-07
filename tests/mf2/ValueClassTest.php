<?php
/**
 * Tests of the mf2\Parser’s value-class datetime parsing methods
 */

namespace mf2\Parser\DT;

// Include Parser.php
$autoloader = require_once dirname(__DIR__) . '/../mf2/Parser.php';

use mf2\Parser,
	PHPUnit_Framework_TestCase,
	DateTime;

class ParserTest extends PHPUnit_Framework_TestCase
{	
	public function setUp()
	{
		date_default_timezone_set('Europe/London');
	}
	
	/**
	 * @group parseDT
	 * @group valueClass
	 */
	public function testYYYY_MM_DD__HH_MM()
	{
		$input = '<div class="h-event"><span class="dt-start"><span class="value">2012-10-07</span> at <span class="value">21:18</span></span></div>';
		$parser = new Parser($input);
		$output = $parser -> parse();
		
		$datetime = new DateTime('2012-10-07T21:18');
		
		$this -> assertArrayHasKey('h-event', $output);
		$this -> assertArrayHasKey('dt-start', $output['h-event'][0]);
		$this -> assertEquals($datetime -> format(DateTime::ISO8601), $output['h-event'][0]['dt-start'][0] -> format(DateTime::ISO8601));
	}
	
	/**
	 * @group parseDT
	 * @group valueClass
	 */
	public function testAbbrYYYY_MM_DD__HH_MM()
	{
		$input = '<div class="h-event"><span class="dt-start"><abbr class="value" title="2012-10-07">some day</a> at <span class="value">21:18</span></span></div>';
		$parser = new Parser($input);
		$output = $parser -> parse();
		
		$datetime = new DateTime('2012-10-07T21:18');
		
		$this -> assertArrayHasKey('h-event', $output);
		$this -> assertArrayHasKey('dt-start', $output['h-event'][0]);
		$this -> assertEquals($datetime -> format(DateTime::ISO8601), $output['h-event'][0]['dt-start'][0] -> format(DateTime::ISO8601));
	}
	
	/**
	 * @group parseDT
	 * @group valueClass
	 */
	public function testYYYY_MM_DD__HHpm()
	{
		$input = '<div class="h-event"><span class="dt-start"><span class="value">2012-10-07</span> at <span class="value">9pm</span></span></div>';
		$parser = new Parser($input);
		$output = $parser -> parse();
		
		$datetime = new DateTime('2012-10-07T21:00');
		
		$this -> assertArrayHasKey('h-event', $output);
		$this -> assertArrayHasKey('dt-start', $output['h-event'][0]);
		$this -> assertEquals($datetime -> format(DateTime::ISO8601), $output['h-event'][0]['dt-start'][0] -> format(DateTime::ISO8601));
	}
}

// EOF tests/mf2/ValueClassTest.php