<?php

/**
 * microformats test suite for php-mf2
 *
 * Before running this test suite, ensure that you run composer install
 * to install the microformats/tests repository of tests.
 * @see https://github.com/microformats/tests
 *
 * CLI usage from library root directory: php ./tests/test-suite/test-suite.php
 *
 * This will look through the test directories for .html files that
 * represent a test and the corresponding .json file that represent
 * the expected output. If a test fails, a message is displayed along
 * with the parsed output and the expected output, both in array format.
 *
 * Individual test suites may be run by specifying the relative path within the repo.
 * For example, to run only the 'microformats-v2' tests:
 *   php ./tests/test-suite/test-suite.php microformats-v2
 */

namespace Mf2\Parser\TestSuite;

use Mf2\Parser;
use Mf2;

error_reporting(E_ALL);
require dirname(__DIR__) . '/../vendor/autoload.php';

class TestSuite
{
	private $path = '';

	private $suites;

	private $tests_total = 0;

	private $tests_passed = 0;

	private $tests_failed = 0;

	/**
	 * This method constructs the TestSuite
	 * @param string $path: path to test-suite-data
	 * @access public
	 */
	public function __construct($path = '')
	{
		$path = './vendor/mf2/tests/tests/' . $path;

		if ( !file_exists($path) )
		{
			echo sprintf('Specified path was not found: %s', $path), "\n";
			exit;
		}

		$this->path = $path;
	} # end method __construct()


	/**
	 * This method runs the test suite
	 * @param array
	 * @access public
	 * @return bool
	 */
	public function start()
	{
		$directory = new \RecursiveDirectoryIterator($this->path, \RecursiveDirectoryIterator::SKIP_DOTS);
		$iterator = new \RecursiveIteratorIterator($directory);
		$this->suites = new \RegexIterator($iterator, '/^.+\.html$/i', \RecursiveRegexIterator::GET_MATCH);

		foreach ( $this->suites as $suite )
		{
			$this->runTest(reset($suite));
		}

		echo sprintf('Total tests: %d', $this->tests_total), "\n";
		echo sprintf('Passed: %d', $this->tests_passed), "\n";
		echo sprintf('Failed: %d', $this->tests_failed), "\n";

		return TRUE;
	} # end method start()


	/**
	 * This method handles running a test
	 * @param string $path: path to the test's HTML file
	 * @access public
	 * @return bool
	 */
	public function runTest($path)
	{
		$test_name = basename($path, '.html');
		echo sprintf('Running test: %s.', $test_name), "\n";

		$dirname = dirname($path);
		$json_file = $dirname . '/' . $test_name . '.json';

		if ( !file_exists($json_file) )
		{
			echo sprintf('Halting test: %s. No json file found.', $test_name), "\n";
			return FALSE;
		}

		$input = file_get_contents($path);
		$expected_output = json_decode(file_get_contents($json_file), TRUE);

		$parser = new Parser($input, '', TRUE);
		$output = $parser->parse(TRUE);

		$test_differences = $this->array_diff_assoc_recursive($expected_output, $output);

		# if: test passed
		if ( empty($test_differences) )
		{
			$this->tests_passed++;
		}
		# else: test failed
		else
		{
			echo sprintf('Test failed: %s', $test_name), "\n\n";
			echo sprintf('Parsed: %s', print_r($output, TRUE)), "\n";
			echo sprintf('Expected: %s', print_r($expected_output, TRUE)), "\n";
			echo sprintf('Differences: %s', print_r($test_differences, TRUE)), "\n";
			$this->tests_failed++;
		} # end if

		return TRUE;
	} # end method runTest()


	/**
	 * This method recursively compares two arrays and returns the difference
	 * @see http://us2.php.net/manual/en/function.array-diff-assoc.php
	 * @param array $array1
	 * @param array $array2
	 * @access public
	 * @return array
	 */
	public function array_diff_assoc_recursive($array1, $array2, $canonicalize = true)
	{
		$difference = array();

		# loop: each key in first array
		foreach ( $array1 as $key => $value )
		{

			# if: nested array
			if ( is_array($value) )
			{

				# if: mis-match
				if ( !isset($array2[$key]) || !is_array($array2[$key]) )
				{
					$difference[$key] = $value;
				}
				# else: recursive
				else
				{
					$recursive_diff = $this->array_diff_assoc_recursive($value, $array2[$key]);

					if ( !empty($recursive_diff) )
					{
						$difference[$key] = $recursive_diff;
					}

				}

			}
			# else if: numeric key, non-array value
			else if ( is_numeric($key) && !is_array($value) )
			{

				# if: check for value anywhere in second array (JSON is orderless)
				if ( !in_array($value, $array2) )
				{
					$difference[$key] = $value;
				}

			}
			# else if: associative key
			else if ( !array_key_exists($key, $array2) || $array2[$key] !== $value )
			{
				$difference[$key] = $value;
			}

		} # end loop

		return $difference;
	} # end method array_diff_assoc_recursive()


	/**
	 * DEPRECATED
	 * This method handles running a test suite
	 * @param string $path: path to the suite's JSON file
	 * @access public
	 * @return bool
	 */
	public function runSuite($path)
	{
		$suite = json_decode(file_get_contents($path));
		echo sprintf('Running %s.', $suite->name), "\n";

		$iterator = new \DirectoryIterator(dirname($path));

		# loop: each file in the test suite
		foreach ( $iterator as $file )
		{

			# if: file is a sub-directory and not a dot-directory
			if ( $file->isDir() && !$file->isDot() )
			{
				$this->tests_total++;

				$path_of_test = $file->getPathname() . '/';

				$test = json_decode(file_get_contents($path_of_test . 'test.json'));
				$input = file_get_contents($path_of_test . 'input.html');
				$expected_output = json_decode(file_get_contents($path_of_test . 'output.json'), TRUE);

				$parser = new Parser($input, '', TRUE);
				$output = $parser->parse(TRUE);

				# if: test passed
				if ( $output['items'] === $expected_output['items'] )
				{
					// echo '.'; # can output a dot for successful tests
					$this->tests_passed++;
				}
				# else: test failed
				else
				{
					echo sprintf('"%s" failed.', $test->name), "\n\n";
					echo sprintf('Parsed: %s', print_r($output, TRUE)), "\n";
					echo sprintf('Expected: %s', print_r($expected_output, TRUE)), "\n";
					$this->tests_failed++;
				} # end if

			} # end if

		} # end loop

		return TRUE;
	} # end method runSuite()

}

$path = ( empty($argv[1]) ) ? '' : $argv[1];
$TestSuite = new TestSuite($path);
$TestSuite->start(); # run tests
