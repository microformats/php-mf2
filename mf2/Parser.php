<?php

namespace mf2;

use DOMDocument,
	DOMElement,
	DOMXPath,
	DOMNode,
	DOMNodeList,
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
	 *	@param mixed $input Could be a string URL (TODO), a string of DOM or a DOMDocument
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
	 *	Given the value of @class, get the relevant mf classname.
	 *	Matches the first if there are multiple.
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
	 */
	static function mfNameFromElement(\DOMElement $e, $prefix='h-')
	{
		$class = $e -> getAttribute('class');
		return Parser::mfNameFromClass($class, $prefix);
	}
	
	/**
	 *	Checks to see if a DOMElement has already been parsed
	 */
	static function mfElementParsed(\DOMElement $e, $type)
	{
		return ($e -> getAttribute('data-' . $type . '-parsed') == 'true');
	}
	
	// !Parsing Functions
	/**
	 *	Given an element with class="p-*", get it’s value
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
	 */
	public function parseU(\DOMElement $u)
	{
		if ($u -> tagName == 'a' and $u -> getAttribute('href') !== null)
		{
			$uValue = $u -> getAttribute('href');
		}
		elseif ($u -> tagName == 'img' and $u -> getAttribute('src') !== null)
		{
			$u -> getAttribute('src');
		}
		else
		{
			// TODO: Check for element contents == a valid URL
			$uValue = false;
		}
		
		$host = parse_url($uValue, PHP_URL_HOST);
		$scheme = parse_url($uValue, PHP_URL_SCHEME);
		if (empty($host) or empty($host) and !empty($this -> baseurl))
		{
			$uValue = $this -> baseurl . $uValue;
		}
		
		return $uValue;
	}
	
	/**
	 *	Given an element with class="dt-*", get the value of the datetime as a php date object
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
					if (!empty($alt)) $dateParts[] = $datetime;
				}
				elseif ($e -> tagName == 'data')
				{
					// Use @value, otherwise innertext
					$value = $e -> hasAttribute('value') ? $e -> getAttribute('value') : trim($e -> nodeValue);
					if (!empty($value)) $dateParts[] = $datetime;
				}
				elseif ($e -> tagName == 'abbr')
				{
					// Use @title, otherwise innertext
					$title = $e -> hasAttribute('title') ? $e -> getAttribute('title') : trim($e -> nodeValue);
					if (!empty($title)) $dateParts[] = $datetime;
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
					if (preg_match('/\d{2}:\d{2}(Z?[+|-]\d{2}:?\d{2})?/', $part) and empty($timePart))
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
				echo 'Using <time> @datetime';
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
	 */
	public function parseH(\DOMElement $e)
	{
		// If it’s already been parsed (e.g. is a child mf), skip
		if (Parser::mfElementParsed($e, 'h')) return false;
		
		// Get current µf name
		$mfName = Parser::mfNameFromElement($e);
		
		echo '<div style="border-left: 2px black solid; padding-left: 1em;"><h3>Handling ' . $mfName . '</h3>';
		
		// Handle nested microformats (h-*)
		foreach ($this -> xpath -> query('.//*[contains(@class,"h-")]', $e) as $subMF)
		{
			// Parse
			$result = $this -> parseH($subMF);
			
			// Make sure this sub-mf won’t get parsed as a top level mf
			$subMF -> setAttribute('data-h-parsed', 'true');
		}
		
		// Handle p-*
		foreach ($this -> xpath -> query('.//*[contains(@class,"p-")]', $e) as $p)
		{
			if (Parser::mfElementParsed($p, 'p')) continue;
			
			$pValue = $this -> parseP($p);
			
			echo '<p><b>' . Parser::mfNameFromElement($p, 'p-') . '</b> (plaintext): ' . $pValue;
			// Make sure this sub-mf won’t get parsed as a top level mf
			$p -> setAttribute('data-p-parsed', 'true');
		}
		
		// Handle u-*
		foreach ($this -> xpath -> query('.//*[contains(@class,"u-")]', $e) as $u)
		{
			if (Parser::mfElementParsed($u, 'u')) continue;
			
			$uValue = $this -> parseU($u);
			
			echo '<p><b>' . Parser::mfNameFromElement($u, 'u-') . '</b> (URL): ' . $uValue;
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
				echo '<p><b>' . Parser::mfNameFromElement($dt, 'dt-') . '</b> (DateTime): ' . $dtValue -> format(\DateTime::ISO8601);
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
				echo '<p><b>' . Parser::mfNameFromElement($em, 'e-') . '</b> (Embedded): <code>' . htmlspecialchars($eValue) . '</code>';
			}
			// Make sure this sub-mf won’t get parsed as a top level mf
			$em -> setAttribute('data-e-parsed', 'true');
		}
		
		echo '</div>';
	}

	/**
	 *	Kicks off the parsing routine
	 */
	public function parse()
	{
		foreach ($this -> xpath -> query('//*[contains(@class,"h-")]') as $node)
		{
			// For each microformat
			$result = $this -> parseH($node);
		}
	}
}

// EOF mf2/Parser.php