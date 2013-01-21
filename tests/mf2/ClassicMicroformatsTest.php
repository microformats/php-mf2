<?php

/**
 * Tests of the parsing methods within mf2\Parser
 */

namespace mf2\Parser\test;

// Include Parser.php
$autoloader = require_once dirname(__DIR__) . '/../mf2/Parser.php';

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

    public function testConvertClassicVCard() {
        $input = '<div class="vcard">
 <a class="url fn" href="http://tantek.com/">Tantek Çelik</a>
</div>';
        $expected = '<div class="h-card">
 <a class="u-url p-name" href="http://tantek.com/">Tantek Çelik</a>
</div>';

        $parser = new Parser('');
        $converted = $parser->convertClassic($input);

        $this->assertEquals($expected, $converted);
    }
    
    public function testConvertsClassicHAtom() {
        $input = '<div class="hfeed">
            <p class="p-name">problem preventer</p>
 <div class="hentry">
  <h1 class="entry-title">The Title</h1>
  <p class="entry-summary">This is the summary</p>
  <div class="entry-content"><p>Some content</p></div>
 </div>
</div>';
        
        $expected = [
            'items' => [
                [
                    'type' => ['h-feed'],
                    'properties' => [
                        'name' => ['problem preventer']
                    ],
                    'children' => [
                        [
                            'type' => ['h-entry'],
                            'properties' => [
                                'name' => ['The Title'],
                                'summary' => ['This is the summary'],
                                'content' => ['<p>Some content</p>']
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        $parser = new Parser($input, null, true);
        $parsed = $parser->parse();
        
        $this->assertEquals($expected, $parsed);
    }

}