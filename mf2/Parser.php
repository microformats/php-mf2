<?php

namespace mf2;

use DOMDocument,
	DOMElement,
	DOMXPath,
	DOMNode,
	DOMNodeList,
	DateTime,
	Exception;

class Parser
{
	/**
	 *	The baseurl (if any) to use for this parse
	 */
	public $baseurl;
	
	/**
	 *	A DOMXPath object which can be used to query over any fragment
	 */
	protected $xpath;
	
	/**
	 *	Constructor
	 *	@param mixed $input The data to parse. Can be a string URL (TODO), a string of DOM or a DOMDocument
	 */
	public function __construct($input, $baseurl=null)
	{
		// TODO: Check is URL
		
		// For the moment: assume string = string of HTML
		if(is_string($input))
		{
			$doc = new DOMDocument();
			@$doc -> loadHTML($input); // TODO: handle seriously malformed HTML better
		}
		elseif (is_a($input, 'DOMDocument'))
		{
			$doc = $input;
		}
		
		$this -> xpath = new DOMXPath($doc);
		
		// TODO: Check for <base> if $baseURL not supplied
		$this -> baseurl = $baseurl;
	}
	
	// !Utility Functions
	
	/**
	 *	Given the value of @class, get the relevant mf classname (e.g. h-card, p-name).
	 *	Matches the first if there are multiple.
	 *
	 *	@param string $class A space delimited list of classnames
	 *	@param string $prefix The prefix to look for
	 *	@return mixed The prefixed name of the first microfomats class found or false
	 */
	static function mfNameFromClass($class, $prefix='h-')
	{
		$classes = explode(' ', $class);
		foreach ($classes as $classname)
		{
			if (strstr($classname, $prefix))
			{
				return $classname;
			}
		}
		return false;
	}
	
	/**
	 *	Wraps mf_name_from_class to handle an element as input (common)
	 *	
	 *	@param DOMElement $e The element to get the classname for
	 *	@param string $prefix The prefix to look for
	 *	@return mixed See return value of mf2\Parser::mfNameFromClass()
	 */
	static function mfNameFromElement(\DOMElement $e, $prefix='h-')
	{
		$class = $e -> getAttribute('class');
		return Parser::mfNameFromClass($class, $prefix);
	}
	
	/**
	 *	Checks to see if a DOMElement has already been parsed
	 *
	 *	@param DOMElement $e The element to check
	 *	@param string $type	The type of parsing to check for
	 *	@return bool Whether or not $e has already been parsed as $type
	 */
	static function mfElementParsed(\DOMElement $e, $type)
	{
		return ($e -> getAttribute('data-' . $type . '-parsed') == 'true');
	}
	
	// !Parsing Functions
	/**
	 *	Given an element with class="p-*", get it’s value
	 *
	 *	@param DOMElement $p The element to parse
	 *	@return string The plaintext value of $p, dependant on type
	 */
	public function parseP(\DOMElement $p)
	{
		if ($p -> tagName == 'img')
		{
			$pValue = $p -> getAttribute('alt');
		}
		elseif ($p -> tagName == 'abbr' and $p -> hasAttribute('title'))
		{
			$pValue = $p -> getAttribute('title');
		}
		else
		{
			// Use innertext
			$pValue = trim($p -> nodeValue);
		}
		
		return $pValue;
	}
	
	/**
	 *	Given an element with class="u-*", get the value of the URL
	 *
	 *	@param DOMElement $u The element to parse
	 *	@return string The plaintext value of $u, dependant on type
	 */
	public function parseU(\DOMElement $u)
	{
		if ($u -> tagName == 'a' and $u -> getAttribute('href') !== null)
		{
			$uValue = $u -> getAttribute('href');
		}
		elseif ($u -> tagName == 'img' and $u -> getAttribute('src') !== null)
		{
			$uValue = $u -> getAttribute('src');
		}
		else
		{
			// TODO: Check for element contents == a valid URL
			$uValue = false;
		}
		
		if ($uValue !== false)
		{
			$host = parse_url($uValue, PHP_URL_HOST);
			$scheme = parse_url($uValue, PHP_URL_SCHEME);
			if (empty($host) and empty($host) and !empty($this -> baseurl))
			{
				$uValue = $this -> baseurl . $uValue;
			}
		}
		
		return $uValue;
	}
	
	/**
	 *	Given an element with class="dt-*", get the value of the datetime as a php date object
	 *
	 *	@param DOMElement $dt The element to parse
	 *	@return DateTime An object representing $dt
	 */
	public function parseDT(\DOMElement $dt)
	{
		// TODO: check for value-title pattern (http://microformats.org/wiki/vcp#Parsing_value_from_a_title_attribute)
		
		// Check for value-class pattern
		$valueClassChildren = $this -> xpath -> query('.//*[contains(@class, "value")]', $dt);
		$dtValue = false;
		
		if ($valueClassChildren -> length > 0)
		{
			// They’re using value-class (awkward bugger :)
			$dateParts = array();
			
			foreach ($valueClassChildren as $e)
			{
				if ($e -> tagName == 'img' or $e -> tagName == 'area')
				{
					// Use @alt
					$alt = $e -> getAttribute('alt');
					if (!empty($alt)) $dateParts[] = $alt;
				}
				elseif ($e -> tagName == 'data')
				{
					// Use @value, otherwise innertext
					$value = $e -> hasAttribute('value') ? $e -> getAttribute('value') : trim($e -> nodeValue);
					if (!empty($value)) $dateParts[] = $value;
				}
				elseif ($e -> tagName == 'abbr')
				{
					// Use @title, otherwise innertext
					$title = $e -> hasAttribute('title') ? $e -> getAttribute('title') : trim($e -> nodeValue);
					if (!empty($title)) $dateParts[] = $title;
				}
				elseif ($e -> tagName == 'del' or $e -> tagName == 'ins' or $e -> tagName == 'time')
				{
					// Use @datetime if available, otherwise innertext
					$dtAttr = ($e -> hasAttribute('datetime')) ? $e -> getAttribute('datetime') : trim($e -> nodeValue);
					if (!empty($dtAttr)) $dateParts[] = $dtAttr;
				}
				else
				{
					// Use innertext
					if (!empty($e -> nodeValue)) $dateParts[] = trim($e -> nodeValue);
				}
			}
			
			// Look through dateParts
			$datePart = '';
			$timePart = '';
			foreach ($dateParts as $part)
			{
				// Is this part a full ISO8601 datetime?
				if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(Z?[+|-]\d{2}:?\d{2})?$/', $part))
				{
					// Break completely, we’ve got our value
					$dtValue = date_create($part);
					break;
				}
				else
				{
					// Is the current part a valid time(+TZ?) AND no other time reprentation has been found?
					if ((preg_match('/\d{2}:\d{2}(Z?[+|-]\d{2}:?\d{2})?/', $part) or preg_match('/\d{1,2}[a|p]m/', $part)) and empty($timePart))
					{
						$timePart = $part;
					}
					elseif (preg_match('/\d{4}-\d{2}-\d{2}/', $part) and empty($datePart))
					{
						// Is the current part a valid date AND no other date reprentation has been found?
						$datePart = $part;
					}
				}
			}
			
			// If we have both a $datePart and $timePart, create date from those
			if (!empty($datePart) and !empty($timePart))
			{
				$dateTime = $datePart . ' ' . $timePart;
				$dtValue = date_create($dateTime);
			}
			// Otherwise, fail (TODO: perhaps try to use any parts we do have to build an approximate date?)
		}
		else
		{
			// Not using value-class (phew).
			if ($dt -> tagName == 'img' or $dt -> tagName == 'area')
			{
				// Use @alt
				// Is it an entire dt?
				$alt = $dt -> getAttribute('alt');
				if (!empty($alt))
				{
					$dtValue = date_create($alt);
				}
			}
			elseif ($dt -> tagName == 'data')
			{
				// Use @value, otherwise innertext
				// Is it an entire dt?
				$value = $dt -> getAttribute('value');
				if (!empty($value))
				{
					$dtValue = date_create($value);
				}
				else
				{
					$dtValue = date_create($dt -> nodeValue);
				}
			}
			elseif ($dt -> tagName == 'abbr')
			{
				// Use @title, otherwise innertext
				// Is it an entire dt?
				$title = $dt -> getAttribute('title');
				if (!empty($title))
				{
					$dtValue = date_create($title);
				}
				else
				{
					$dtValue = date_create($dt -> nodeValue);
				}
			}
			elseif ($dt -> tagName == 'del' or $dt -> tagName == 'ins' or $dt -> tagName == 'time')
			{
				// Use @datetime if available, otherwise innertext
				// Is it an entire dt?
				$dtAttr = $dt -> getAttribute('datetime');
				if (!empty($dtAttr))
				{
					$dtValue = date_create($dtAttr);
				}
				else
				{
					$dtValue = date_create($dt -> nodeValue);
				}
			}
			else
			{
				// Use innertext
				$dtValue = date_create($dt -> nodeValue);
			}
		}
		
		// Whatever happened, $dtValue is now either a \DateTime or false.
		return $dtValue;
	}
	
	/**
	 *	Given the root element of some embedded markup, return a string representing that markup
	 *
	 *	@param DOMElement $e The element to parse
	 *	@return string $e’s innerHTML
	 */
	public function parseE(\DOMElement $e)
	{
		$return = '';
		foreach ($e -> childNodes as $node)
		{
			$return .= $node -> C14N();
		}
		return $return;
	}
	
	/**
	 *	Recursively parse microformats
	 *
	 *	@param DOMElement $e The element to parse
	 *	@return array A representation of the values contained within microformat $e
	 */
	public function parseH(\DOMElement $e)
	{
		// If it’s already been parsed (e.g. is a child mf), skip
		if (Parser::mfElementParsed($e, 'h')) return false;
		
		// Get current µf name
		$mfName = Parser::mfNameFromElement($e);
		
		// Initalise var to store the representation in
		$return = array();
		
		// DEBUG
		//echo '<div style="border-left: 2px black solid; padding-left: 1em;"><h3>Handling ' . $mfName . '</h3>';
		
		// Handle nested microformats (h-*)
		foreach ($this -> xpath -> query('.//*[contains(@class,"h-")]', $e) as $subMF)
		{
			// Parse
			$result = $this -> parseH($subMF);
			
			// Add the value to the array for this property type
			$return[Parser::mfNameFromElement($subMF)][] = $result;
			
			// Make sure this sub-mf won’t get parsed as a top level mf
			$subMF -> setAttribute('data-h-parsed', 'true');
		}
		
		// Handle p-*
		foreach ($this -> xpath -> query('.//*[contains(@class,"p-")]', $e) as $p)
		{
			if (Parser::mfElementParsed($p, 'p')) continue;
			
			$pValue = $this -> parseP($p);
			
			// Add the value to the array for this property type
			$return[Parser::mfNameFromElement($p, 'p-')][] = $pValue;
			
			// DEBUG
			//echo '<p><b>' . Parser::mfNameFromElement($p, 'p-') . '</b> (plaintext): ' . $pValue;
			
			// Make sure this sub-mf won’t get parsed as a top level mf
			$p -> setAttribute('data-p-parsed', 'true');
		}
		
		// Handle u-*
		foreach ($this -> xpath -> query('.//*[contains(@class,"u-")]', $e) as $u)
		{
			if (Parser::mfElementParsed($u, 'u')) continue;
			
			$uValue = $this -> parseU($u);
			
			// Add the value to the array for this property type
			$return[Parser::mfNameFromElement($u, 'u-')][] = $uValue;
			
			// DEBUG
			//echo '<p><b>' . Parser::mfNameFromElement($u, 'u-') . '</b> (URL): ' . $uValue;
			
			// Make sure this sub-mf won’t get parsed as a top level mf
			$u -> setAttribute('data-u-parsed', 'true');
		}
		
		// Handle dt-*
		foreach ($this -> xpath -> query('.//*[contains(@class,"dt-")]', $e) as $dt)
		{
			if (Parser::mfElementParsed($dt, 'dt')) continue;
			
			$dtValue = $this -> parseDT($dt);
			
			if ($dtValue)
			{
				// Add the value to the array for this property type
				$return[Parser::mfNameFromElement($dt, 'dt-')][] = $dtValue;
			
				// DEBUG
				// echo '<p><b>' . Parser::mfNameFromElement($dt, 'dt-') . '</b> (DateTime): ' . $dtValue -> format(\DateTime::ISO8601);
			}
			// Make sure this sub-mf won’t get parsed as a top level mf
			$dt -> setAttribute('data-dt-parsed', 'true');
		}
		
		// TODO: Handle e-* (em)
		foreach ($this -> xpath -> query('.//*[contains(@class,"e-")]', $e) as $em)
		{
			if (Parser::mfElementParsed($em, 'e')) continue;
			
			$eValue = $this -> parseE($em);
			
			if ($eValue)
			{
				// Add the value to the array for this property type
				$return[Parser::mfNameFromElement($em, 'e-')][] = $eValue;
				
				// DEBUG
				// echo '<p><b>' . Parser::mfNameFromElement($em, 'e-') . '</b> (Embedded): <code>' . htmlspecialchars($eValue) . '</code>';
			}
			// Make sure this sub-mf won’t get parsed as a top level mf
			$em -> setAttribute('data-e-parsed', 'true');
		}
		
		// DEBUG
		//echo '</div>';
		
		// TODO: Any post-processing which needs to happen?
		
		// Return the representation of the µf
		return $return;
	}

	/**
	 *	Kicks off the parsing routine
	 *
	 *	@return array An array containing all the µfs found in the current document
	 */
	public function parse()
	{
		$mfs = array();
		
		foreach ($this -> xpath -> query('//*[contains(@class,"h-")]') as $node)
		{
			// For each microformat
			$result = $this -> parseH($node);
			
			// Add the value to the array for this property type
			$mfs[Parser::mfNameFromElement($node)][] = $result;
		}
		
		return $mfs;
	}
}

// EOF mf2/Parser.php