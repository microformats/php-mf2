<?php

namespace Mf2\Parser\Test;

class MicroformatsTestSuiteTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider mf1TestsProvider
     * @group microformats/tests/mf1
     */
    public function testMf1FromTestSuite($input, $expectedOutput)
    {
        $parser = new \Mf2\Parser($input, 'http://example.com/');
        $this->assertEquals(
            $this->makeComparible(json_decode($expectedOutput, true)),
            $this->makeComparible(json_decode(json_encode($parser->parse()), true))
        );
    }

    /**
     * @dataProvider mf2TestsProvider
     * @group microformats/tests/mf2
     */
    public function testMf2FromTestSuite($input, $expectedOutput)
    {
        $parser = new \Mf2\Parser($input, 'http://example.com/');
        $this->assertEquals(
            $this->makeComparible(json_decode($expectedOutput, true)),
            $this->makeComparible(json_decode(json_encode($parser->parse()), true))
        );
    }

    /**
     * @dataProvider mixedTestsProvider
     * @group microformats/tests/mixed
     */
    public function testMixedFromTestSuite($input, $expectedOutput)
    {
        $parser = new \Mf2\Parser($input, 'http://example.com/');
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
     * * We replace all consecutive whitespace with single space characters in e-* value
     *   properties, to avoid failing tests only because difference in the handing of
     *   extracting textContent.
     **/
    public function makeComparible($array)
    {
        ksort($array);
        foreach ($array as $key => $value) {
            if (gettype($value) === 'array') {
                $array[$key] = $this->makeComparible($value);
            } else if (gettype($value) === 'string') {
                if ($key === 'value' && array_key_exists('html', $array)) {
                    $value = preg_replace('/\s+/', ' ', $value);
                }
                $array[$key] = substr(json_encode($value), 1, -1);
            }
        }
        return $array;
    }

    /**
     * Data provider lists all tests from a specific directory in mf2/tests.
     **/
    public function htmlAndJsonProvider($subFolder = '')
    {
        // Get the actual wanted subfolder.
        $subFolder = '/mf2/tests/tests' . $subFolder;
        // Ripped out of the test-suite.php code:
        $finder = new \RegexIterator(
            new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
                dirname(__FILE__) . '/../../vendor/' . $subFolder,
                \RecursiveDirectoryIterator::SKIP_DOTS
            )),
            '/^.+\.html$/i',
            \RecursiveRegexIterator::GET_MATCH
        );
        // Build the array of separate tests:
        $tests = array();
        foreach ($finder as $key => $value) {
            $dir = realpath(pathinfo($key, PATHINFO_DIRNAME));
            $testname = substr($dir, strpos($dir, $subFolder) + strlen($subFolder) + 1) . '/' . pathinfo($key, PATHINFO_FILENAME);
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

    /**
     * Following three functions are the actual dataProviders used by the test methods.
     */
    public function mf1TestsProvider()
    {
        return $this->htmlAndJsonProvider('/microformats-v1');
    }

    public function mf2TestsProvider()
    {
        return $this->htmlAndJsonProvider('/microformats-v2');
    }

    public function mixedTestsProvider()
    {
        return $this->htmlAndJsonProvider('/microformats-mixed');
    }
}
