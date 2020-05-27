<?php

namespace Mf2\Parser\Test;

class MicroformatsTestSuiteTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider htmlAndJsonProvider
     */
    public function testFromTestSuite($input, $expectedOutput)
    {
        $parser = new \Mf2\Parser($input);
        $this->compareJson(
            json_decode($expectedOutput, true),
            $parser->parse()
        );
    }

    /**
     * Objects within JSON are unordered.
     * Check if all keys from the correct one are present (in any order) in our output.
     * Then recursively check the contents of those properties.
     **/
    public function compareJson($correct, $test)
    {
        if (gettype($correct) === 'array'  && $this->isAssoc($correct)) {
            foreach ($correct as $key => $value) {
                $this->assertArrayHasKey($key, $test);
                $this->compareJson($value, $test[$key]);
            }
            foreach (array_diff(array_keys($test), array_keys($correct)) as $fault) {
                // This will always fail, but we want to know in which tests this happens!
                $this->assertArrayHasKey(
                    $fault,
                    $correct,
                    'The parser output included an extra property compared.'
                );
            }
        } else {
            $this->assertEquals($correct, $test);
        }
    }

    /**
     * Check if the encountered array is an associative array (has string keys).
     * @see https://stackoverflow.com/a/173479
     **/
    public function isAssoc($array)
    {
        return array() !== $array && array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Data provider lists all tests from mf2/tests.
     **/
    public function htmlAndJsonProvider()
    {
        // Ripped out of the test-suite.php code:
        $finder = new \RegexIterator(
            new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
                dirname(__FILE__) . '/../../vendor/mf2/tests/tests',
                \RecursiveDirectoryIterator::SKIP_DOTS
            )),
            '/^.+\.html$/i',
            \RecursiveRegexIterator::GET_MATCH
        );
        // Build the array of separate tests:
        $tests = array();
        foreach ($finder as $key => $value) {
            $dir = realpath(pathinfo($key, PATHINFO_DIRNAME));
            $test = pathinfo($key, PATHINFO_BASENAME);
            $result = pathinfo($key, PATHINFO_FILENAME) . '.json';
            if (is_file($dir . '/' . $result)) {
                $tests[pathinfo($key, PATHINFO_FILENAME)] = array(
                    'input' => file_get_contents($dir . '/' . $test),
                    'expectedOutput' => file_get_contents($dir . '/' . $result)
                );
            }
        }
        return $tests;
    }
}
