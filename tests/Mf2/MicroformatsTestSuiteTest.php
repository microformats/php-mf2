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
        $this->assertEquals(
            $this->makeComparible(json_decode($expectedOutput, true)),
            $this->makeComparible(json_decode(json_encode($parser->parse()), true))
        );
    }

    /**
     * To make arrays coming from JSON more easily comparible by PHPUnit:
     * * We sort arrays by key, normalising them, because JSON objects are unordered.
     * * We json_encode strings, and cut the starting and ending ", so PHPUnit better
     *   shows whitespace characters like tabs and newlines.
     **/
    public function makeComparible($array)
    {
        ksort($array);
        foreach ($array as $key => $value) {
            if (gettype($value) === 'array') {
                $array[$key] = $this->makeComparible($value);
            } else if (gettype($value) === 'string') {
                $array[$key] = substr(json_encode($value), 1, -1);
            }
        }
        return $array;
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
            $testname = substr($dir, strpos($dir, '/mf2/tests/tests/') + 17) . '/' . pathinfo($key, PATHINFO_FILENAME);
            $test = pathinfo($key, PATHINFO_BASENAME);
            $result = pathinfo($key, PATHINFO_FILENAME) . '.json';
            if (is_file($dir . '/' . $result)) {
                $tests[$testname] = array(
                    'input' => file_get_contents($dir . '/' . $test),
                    'expectedOutput' => file_get_contents($dir . '/' . $result)
                );
            }
        }
        return $tests;
    }
}
