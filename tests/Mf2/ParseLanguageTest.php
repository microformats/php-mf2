<?php

/**
 * Tests of the language parsing methods within mf2\Parser
 */

namespace Mf2\Parser\Test;

use Mf2\Parser;
use Mf2;
use PHPUnit_Framework_TestCase;

class ParseLanguageTest extends PHPUnit_Framework_TestCase {

	public function setUp() {
		date_default_timezone_set('Europe/London');
	}

	/**
	 * Test with only <html lang>
	 */
	public function testHtmlLangOnly()
	{
		$input = '<html lang="en"> <div class="h-entry">This test is in English.</div> </html>';
		$parser = new Parser($input);
		$result = $parser->parse();

		$this->assertEquals('en', $result['items'][0]['properties']['html-lang']);
	} # end method testHtmlLangOnly()

	/**
	 * Test with only h-entry lang 
	 */
	public function testHEntryLangOnly()
	{
		$input = '<html> <div class="h-entry" lang="en">This test is in English.</div> </html>';
		$parser = new Parser($input);
		$result = $parser->parse();

		$this->assertEquals('en', $result['items'][0]['properties']['html-lang']);
	} # end method testHEntryLangOnly()

	/**
	 * Test with different <html lang> and h-entry lang 
	 */
	public function testHtmlAndHEntryLang()
	{
		$input = '<html lang="en"> <div class="h-entry" lang="es">Esta prueba está en español.</div> </html>';
		$parser = new Parser($input);
		$result = $parser->parse();

		$this->assertEquals('es', $result['items'][0]['properties']['html-lang']);
	} # end method testHtmlAndHEntryLang()

	/**
	 * Test with different <html lang>, h-entry lang, and h-entry without lang,
	 * which should inherit from the <html lang>
	 */
	public function testMultiLanguageInheritance()
	{
		$input = '<html lang="en"> <div class="h-entry">This test is in English.</div> <div class="h-entry" lang="es">Esta prueba está en español.</div> </html>';
		$parser = new Parser($input);
		$result = $parser->parse();

		$this->assertEquals('en', $result['items'][0]['properties']['html-lang']);
		$this->assertEquals('es', $result['items'][1]['properties']['html-lang']);
	} # end method testMultiLanguageInheritance()

	/**
	 * Test feed with .h-feed lang which contains multiple h-entries of different languages 
	 * (or none specified), which should inherit from the .h-feed lang.
	 */
	public function testMultiLanguageFeed()
	{
		$input = '<html> <div class="h-feed" lang="en"> <h1 class="p-name">Test Feed</h1> <div class="h-entry">This test is in English.</div> <div class="h-entry" lang="es">Esta prueba está en español.</div> <div class="h-entry" lang="fr">Ce test est en français.</div> </html>';
		$parser = new Parser($input);
		$result = $parser->parse();

		$this->assertEquals('en', $result['items'][0]['properties']['html-lang']);
		$this->assertEquals('en', $result['items'][0]['children'][0]['properties']['html-lang']);
		$this->assertEquals('es', $result['items'][0]['children'][1]['properties']['html-lang']);
		$this->assertEquals('fr', $result['items'][0]['children'][2]['properties']['html-lang']);
	} # end method testMultiLanguageFeed()

	/**
	 * Test with language specified in <meta> http-equiv Content-Language
	 */
	public function testMetaContentLanguage()
	{
		$input = '<html> <meta http-equiv="Content-Language" content="es"/> <div class="h-entry">Esta prueba está en español.</div> </html>';
		$parser = new Parser($input);
		$result = $parser->parse();

		$this->assertEquals('es', $result['items'][0]['properties']['html-lang']);
	} # end method testMetaContentLanguage()

}
