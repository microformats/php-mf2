<?php
namespace mf2\Parser\test;

// Include Parser.php
$autoloader = require_once dirname(__DIR__) . '/../mf2/Parser.php';

use mf2\Parser,
	PHPUnit_Framework_TestCase,
	DateTime;

/**
 * Combined Microformats Test
 * 
 * Tests the ability of Parser::parse() to handle nested microformats correctly. More info
 * at http://microformats.org/wiki/microformats-2#combining_microformats
 * 
 * @todo implement
 */
class CombinedMicroformatsTest extends PHPUnit_Framework_TestCase
{	
	public function setUp()
	{
		date_default_timezone_set('Europe/London');
	}
	
	public function testHEventLocationHCard()
	{
		$input = '<div class="h-event">
  <a class="p-name u-url" href="http://indiewebcamp.com/2012">
    IndieWebCamp 2012
  </a>
  from <time class="dt-start">2012-06-30</time> 
  to <time class="dt-end">2012-07-01</time> at 
  <span class="p-location h-card">
    <a class="p-name p-org u-url" href="http://geoloqi.com/">
      Geoloqi
    </a>, 
    <span class="p-street-address">920 SW 3rd Ave. Suite 400</span>, 
    <span class="p-locality">Portland</span>, 
    <abbr class="p-region" title="Oregon">OR</abbr>
  </span>
</div>';
		$expected = '{
  "items": [{ 
    "type": ["h-event"],
    "properties": {
      "name": ["IndieWebCamp 2012"],
      "url": ["http://indiewebcamp.com/2012"],
      "start": ["2012-06-30"],
      "end": ["2012-07-01"],
      "location": [{
        "value": "Geoloqi, 920 SW 3rd Ave. Suite 400, Portland, OR",
        "type": ["h-card"],
        "properties": {
          "name": ["Geoloqi"],
          "org": ["Geoloqi"],
          "url": ["http://geoloqi.com/"],
          "street-address": ["920 SW 3rd Ave. Suite 400"],
          "locality": ["Portland"],
          "region": ["Oregon"]
        }
      }]
    }
  }]
}';
		
		$parser = new Parser($input);
		$output = $parser -> parse();
		
		$this -> assertJsonStringEqualsJsonString(json_encode($output), $expected);
	}
}

// EOF