<?php

namespace Mf2\Parser\Test;

use Mf2\Parser;
use Mf2;
use PHPUnit_Framework_TestCase;

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
	
	public function testUnicodeTrim() {
		$this->assertEquals('thing', Mf2\unicodeTrim('  thing  '));
		$this->assertEquals('thing', Mf2\unicodeTrim('			thing			'));
		$this->assertEquals('thing', Mf2\unicodeTrim(mb_convert_encoding(' &nbsp; thing &nbsp; ', 'UTF-8', 'HTML-ENTITIES') ));
	}
	
	public function testMicroformatNameFromClassReturnsFullRootName() {
		$expected = array('h-card');
		$actual = Mf2\mfNamesFromClass('someclass h-card someotherclass', 'h-');

		$this->assertEquals($expected, $actual);
	}

	public function testMicroformatNameFromClassHandlesMultipleHNames() {
		$expected = array('h-card', 'h-person');
		$actual = Mf2\mfNamesFromClass('someclass h-card someotherclass h-person yetanotherclass', 'h-');

		$this->assertEquals($expected, $actual);
	}

	public function testMicroformatStripsPrefixFromPropertyClassname() {
		$expected = array('name');
		$actual = Mf2\mfNamesFromClass('someclass p-name someotherclass', 'p-');

		$this->assertEquals($expected, $actual);
	}

	public function testNestedMicroformatPropertyNameWorks() {
		$expected = array('location', 'author');
		$test = 'someclass p-location someotherclass u-author';
		$actual = Mf2\nestedMfPropertyNamesFromClass($test);
		
		$this->assertEquals($expected, $actual);
	}

	public function testMicroformatNamesFromClassIgnoresPrefixesWithoutNames() {
		$expected = array();
		$actual = Mf2\mfNamesFromClass('someclass h- someotherclass', 'h-');

		$this->assertEquals($expected, $actual);
	}

	public function testMicroformatNamesFromClassHandlesExcessiveWhitespace() {
		$expected = array('h-card');
		$actual = Mf2\mfNamesFromClass('  someclass
			 	h-card 	 someotherclass		   	 ', 'h-');

		$this->assertEquals($expected, $actual);
	}

	public function testMicroformatNamesFromClassIgnoresUppercaseClassnames() {
		$expected = array();
		$actual = Mf2\mfNamesFromClass('H-ENTRY', 'h-');

		$this->assertEquals($expected, $actual);
	}
	
	public function testParseE() {
		$input = '<div class="h-entry"><div class="e-content">Here is a load of <strong>embedded markup</strong></div></div>';
		//$parser = new Parser($input);
		$output = Mf2\parse($input);

		$this->assertArrayHasKey('content', $output['items'][0]['properties']);
		$this->assertEquals('Here is a load of <strong>embedded markup</strong>', $output['items'][0]['properties']['content'][0]['html']);
		$this->assertEquals('Here is a load of embedded markup', $output['items'][0]['properties']['content'][0]['value']);
	}
	
	public function testParseEResolvesRelativeLinks() {
		$input = '<div class="h-entry"><p class="e-content">Blah blah <a href="/a-url">thing</a>. <object data="/object"></object> <img src="/img" /></p></div>';
		$parser = new Parser($input, 'http://example.com');
		$output = $parser->parse();
		
		$this->assertEquals('Blah blah <a href="http://example.com/a-url">thing</a>. <object data="http://example.com/object"></object> <img src="http://example.com/img"></img>', $output['items'][0]['properties']['content'][0]['html']);
		$this->assertEquals('Blah blah thing.  http://example.com/img', $output['items'][0]['properties']['content'][0]['value']);
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
		$output = $parser->parse();
		
		$this->assertEquals('<p>', $output['items'][0]['properties']['name'][0]);
		$this->assertEquals('<dt>', $output['items'][0]['properties']['published'][0]);
		$this->assertEquals('<u>', $output['items'][0]['properties']['url'][0]);
	}
	
	public function testHtmlEncodesImpliedProperties() {
		$input = '<a class="h-card" href="&lt;url&gt;"><img src="&lt;img&gt;" />&lt;name&gt;</a>';
		$parser = new Parser($input);
		$output = $parser->parse();
		
		$this->assertEquals('<name>', $output['items'][0]['properties']['name'][0]);
		$this->assertEquals('<url>', $output['items'][0]['properties']['url'][0]);
		$this->assertEquals('<img>', $output['items'][0]['properties']['photo'][0]);
	}
	
	public function testParsesRelValues() {
		$input = '<a rel="author" href="http://example.com">Mr. Author</a>';
		$parser = new Parser($input);
		
		$output = $parser->parse();
		
		$this->assertArrayHasKey('rels', $output);
		$this->assertEquals('http://example.com', $output['rels']['author'][0]);
	}
	
	public function testParsesRelAlternateValues() {
		$input = '<a rel="alternate home" href="http://example.org" hreflang="de", media="screen"></a>';
		$parser = new Parser($input);
		$output = $parser->parse();
		
		$this->assertArrayHasKey('alternates', $output);
		$this->assertEquals('http://example.org', $output['alternates'][0]['url']);
		$this->assertEquals('home', $output['alternates'][0]['rel']);
		$this->assertEquals('de', $output['alternates'][0]['hreflang']);
		$this->assertEquals('screen', $output['alternates'][0]['media']);
	}
	
	public function testParseFromIdOnlyReturnsMicroformatsWithinThatId() {
		$input = <<<EOT
<div class="h-entry"><span class="p-name">Not Included</span></div>

<div id="parse-here">
	<span class="h-card">Included</span>
</div>

<div class="h-entry"><span class="p-name">Not Included</span></div>
EOT;
		
		$parser = new Parser($input);
		$output = $parser->parseFromId('parse-here');
		
		$this->assertCount(1, $output['items']);
		$this->assertEquals('Included', $output['items'][0]['properties']['name'][0]);
	}
	
	/**
	 * Issue #21 github.com/indieweb/php-mf2/issues/21
	 */
	public function testDoesntAddArraysWithOnlyValueForAlreadyParsedNestedMicroformats() {
		$input = <<<EOT
<div class="h-entry">
	<div class="p-in-reply-to h-entry">
		<span class="p-author h-card">Nested Author</span>
	</div>
	
	<span class="p-author h-card">Real Author</span>
</div>
EOT;
		$parser = new Parser($input);
		$output = $parser->parse();
		
		$this->assertCount(1, $output['items'][0]['properties']['author']);
	}
	
	public function testParsesNestedMicroformatsWithClassnamesInAnyOrder() {
		$input = <<<EOT
<div class="h-entry">
	<div class="note- p-in-reply-to h-entry">Name</div>
</div>
EOT;
		$parser = new Parser($input);
		$output = $parser->parse();
		
		$this->assertCount(1, $output['items'][0]['properties']['in-reply-to']);
		$this->assertEquals('Name', $output['items'][0]['properties']['in-reply-to'][0]['properties']['name'][0]);
	}

	/**
	 * @group network
	 */
	public function testFetchMicroformats() {
		$mf = Mf2\fetch('http://waterpigs.co.uk/');
		$this->assertArrayHasKey('items', $mf);

		$mf = Mf2\fetch('http://waterpigs.co.uk/photo.jpg', null, $curlInfo);
		$this->assertNull($mf);
		$this->assertContains('jpeg', $curlInfo['content_type']);
	}

	/**
	* @see https://github.com/indieweb/php-mf2/issues/48
	*/
	public function testIgnoreClassesEndingInHyphen() {
		$input = '<span class="h-entry"> <span class="e-">foo</span> </span>';
		$parser = new Parser($input);
		$output = $parser->parse();

		$this->assertArrayNotHasKey('0', $output['items'][0]['properties']);
	}

	/**
	 * @see https://github.com/indieweb/php-mf2/issues/52
	 * @see https://github.com/tommorris/mf2py/commit/92740deb7e19b8f1e7fbf6bec001cf52f2b07e99
	 */
	public function testIgnoresTemplateElements() {
		$result = Mf2\parse('<template class="h-card"><span class="p-name">Tom Morris</span></template>');
		$this->assertCount(0, $result['items']);
	}

	/**
	 * @see https://github.com/indieweb/php-mf2/issues/53
	 * @see http://microformats.org/wiki/microformats2-parsing#parsing_an_e-_property
	 */
	public function testConvertsNestedImgElementToAltOrSrc() {
		$input = <<<EOT
<div class="h-entry">
	<p class="e-content">It is a strange thing to see a <img alt="five legged elephant" src="/photos/five-legged-elephant.jpg" /></p>
</div>
EOT;
		$result = Mf2\parse($input, 'http://waterpigs.co.uk/articles/five-legged-elephant');
		$this->assertEquals('It is a strange thing to see a five legged elephant', $result['items'][0]['properties']['content'][0]['value']);
	}
}
