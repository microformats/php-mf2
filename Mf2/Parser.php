<?php

namespace Mf2;

use DOMDocument;
use DOMElement;
use DOMXPath;
use DOMNode;
use DOMNodeList;
use SplObjectStorage;
use stdClass;

/**
 * Parse Microformats2
 *
 * Functional shortcut for the commonest cases of parsing microformats2 from HTML.
 *
 * Example usage:
 *
 *     use Mf2;
 *     $output = Mf2\parse('<span class="h-card">Barnaby Walters</span>');
 *     echo json_encode($output, JSON_PRETTY_PRINT);
 *
 * Produces:
 *
 *     {
 *      "items": [
 *       {
 *        "type": ["h-card"],
 *        "properties": {
 *         "name": ["Barnaby Walters"]
 *        }
 *       }
 *      ],
 *      "rels": {}
 *     }
 *
 * @param string|DOMDocument $input The HTML string or DOMDocument object to parse
 * @param string $url The URL the input document was found at, for relative URL resolution
 * @param bool $convertClassic whether or not to convert classic microformats
 * @return array Canonical MF2 array structure
 */
function parse($input, $url = null, $convertClassic = true) {
	$parser = new Parser($input, $url);
	return $parser->parse($convertClassic);
}

/**
 * Fetch microformats2
 *
 * Given a URL, fetches it (following up to 5 redirects) and, if the content-type appears to be HTML, returns the parsed
 * microformats2 array structure.
 *
 * Not that even if the response code was a 4XX or 5XX error, if the content-type is HTML-like then it will be parsed
 * all the same, as there are legitimate cases where error pages might contain useful microformats (for example a deleted
 * h-entry resulting in a 410 Gone page with a stub h-entry explaining the reason for deletion). Look in $curlInfo['http_code']
 * for the actual value.
 *
 * @param string $url The URL to fetch
 * @param bool $convertClassic (optional, default true) whether or not to convert classic microformats
 * @param &array $curlInfo (optional) the results of curl_getinfo will be placed in this variable for debugging
 * @return array|null canonical microformats2 array structure on success, null on failure
 */
function fetch($url, $convertClassic = true, &$curlInfo=null) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		'Accept: text/html'
	));
	$html = curl_exec($ch);
	$info = $curlInfo = curl_getinfo($ch);
	curl_close($ch);

	if (strpos(strtolower($info['content_type']), 'html') === false) {
		// The content was not delivered as HTML, do not attempt to parse it.
		return null;
	}

	# ensure the final URL is used to resolve relative URLs
	$url = $info['url'];

	return parse($html, $url, $convertClassic);
}

function load_dom_document($input, $contentType = "") {
	/* 
	The work of this function concerns detecting encoding and making sure
	DOMDocument honours the correct document encoding. There are
	multiple complications to this, chiefly that DOMDocument's
	priorities differ from the modern HTML standard's. HTML mandates the
	following from most to least authoritative:

	1. Byte order mark
	2. Protocol-specified encoding
	3. Meta element in document
	4. Heuristics (optional)
	5. Default encoding (usually windows-1252)

	DOMDocument, on the other hand, takes encoding from the following
	sources from most to least authoritative:

	1. UTF-16 byte order mark
	2. Meta element in document
	3. UTF-8 byte order mark
	4. Default encoding (ISO 8859-1)

	Thus this function finds the most authoritative encoding in the order
	required by the specification, and then inserts a meta element where 
	necessary i.e. when the document doesn't already have the correct encoding
	in a meta element.

	Finally, in the case of UTF-16 input the only reliable indicator is a
	byte order mark, so we insert one where needed instead of a meta element.
	*/
	$load = function($input) {
		$d = new DOMDocument();
		@$d->loadHTML($input, \LIBXML_NOWARNING);
		return $d;
	};
	// perform an initial parsing of the document. We may parse it again
	//   if the encoding is wrong, or use the result if everything is correct
	$d = $load($input);
	$meta = "";
	$effective = "";
	// determine the encoding which DOMDocument has detected from the document, starting by looking for a UTF-16 BOM
	if (substr($input, 0, 2) === Encoding::BOM_UTF16BE) {
		$effective = "UTF-16BE";
	} elseif (substr($input, 0, 2) === Encoding::BOM_UTF16LE) {
		$effective = "UTF-16LE";
	} else {
		// find the first valid meta element in the document with 
		//   encoding information; even if this initial parsing uses the
		//   wrong encoding, it's more reliable than trying to do this
		//   with the string directly
		// we keep note of both what DOMDocument understands (the $effective
		//   encoding), as well as what the standard says is valid (the $meta
		//   encoding) as the two can differ
		foreach ($d->getElementsByTagName("meta") as $e) {
			$meta = Encoding::matchEncodingLabel($e->getAttribute("charset"));
			if (strlen($meta)) {
				$effective = Encoding::matchEncodingLabel($e->getAttribute("charset"), true);
				break;
			}
			if (strtolower(trim($e->getAttribute("http-equiv"))) === "content-type") {
				$candidate = Encoding::parseContentType($e->getAttribute("content"));
				$meta = Encoding::matchEncodingLabel($candidate['charset']);
				if (strlen($meta)) {
					$effective = Encoding::matchEncodingLabel($candidate['charset'], true);
					break;
				}
			}
		}
		// if no effective encoding was found, check for a UTF-8 BOM
		if (!$effective && substr($input, 0, 3) === Encoding::BOM_UTF8) {
			$effective = "UTF-8";
		}
	}
	// now find the authoritative encoding according to the standard HTML order
	$uthoritative = "";
	// start by looking for BOMs
	if (substr($input, 0, 3) === Encoding::BOM_UTF8) {
		$authoritative = "UTF-8";
	} elseif (substr($input, 0, 2) === Encoding::BOM_UTF16BE) {
		$authoritative = "UTF-16BE";
	} elseif (substr($input, 0, 2) === Encoding::BOM_UTF16LE) {
		$authoritative = "UTF-16LE";
	} else {
		// now parse the HTTP Content-Type looking for an encoding
		$candidate = Encoding::parseContentType($contentType);
		$candidate = Encoding::matchEncodingLabel($candidate['charset']);
		if ($candidate) {
			$authoritative = $candidate;
		} elseif ($meta) {
			// if the document has a valid <meta> element, use that
			$authoritative = $meta;
		} elseif (preg_match(Encoding::UTF8_PATTERN, $input)) {
			// if the string is valid UTF-8, we can use this
			$authoritative = "UTF-8";
		} else {
			// otherwise use the default encoding
			$authoritative = Encoding::DEFAULT_ENCODING;
		}
	}
	// if the authoritative encoding does not match the encoding currently in
	//   the document, add an appropriate meta tag or (for UTF-16) a BOM
	if ($authoritative !== $effective) {
		// some canonical names are not understood by some environments, so we use aliases where needed
		if (array_key_exists($authoritative, Encoding::ENCODING_ALIAS_MAP)) {
			$authoritative = Encoding::ENCODING_ALIAS_MAP[$authoritative];
		}
		if ($authoritative === "UTF-16BE") {
			if (substr($input, 0, 2) !== Encoding::BOM_UTF16BE) {
				// add a BOM and reparse
				$d = $load(Encoding::BOM_UTF16BE.$input);
			}
		} elseif ($authoritative === "UTF-16LE") {
			if (substr($input, 0, 2) !== Encoding::BOM_UTF16LE) {
				// add a BOM and reparse
				$d = $load(Encoding::BOM_UTF16LE.$input);
			}
		} elseif ($authoritative === "replacement") {
			// the replacement encoding is actually a denylist of problematic encodings; the document is reduced to a single replacement character
			$d = $load(Encoding::BOM_UTF8."\xEF\xBF\xBD");
		} else {
			$offset = 0;
			// look for an HTML <head> element and insert the meta tag within
			//   it, or at the nearest appropriate point; if the tag is 
			//   inserted too soon, attributes on <head> or <html> (which
			//   may hold mf data) can be stripped
			if (preg_match(Encoding::HTML_HEADER_PATTERN, $input, $match)) {
				$offset = strlen($match[0]);
			}
			$tag = '<meta http-equiv="Content-Type" content="text/html;charset=' . $authoritative . '">';
			$d = $load(substr($input, 0, $offset) . $tag . substr($input, $offset));
		}
	}
	return $d;
}

/**
 * Collapse Whitespace
 *
 * Collapses any sequences of whitespace within a string into a single space
 * character.
 *
 * @deprecated since v0.2.3
 * @param string $str
 * @return string
 */
function collapseWhitespace($str) {
	return preg_replace('/[\s|\n]+/', ' ', $str);
}

function unicodeTrim($str) {
	// the binary sequence C2A0 is a UTF-8 non-breaking space character
	$str = preg_replace('/^(?:\s|\x{C2}\x{A0})+/', '', $str);
	return preg_replace('/(?:\s|\x{C2}\x{A0})+$/', '', $str);
}

/**
 * Microformat Name From Class string
 *
 * Given the value of @class, get the relevant mf classnames (e.g. h-card,
 * p-name).
 *
 * @param string $class A space delimited list of classnames
 * @param string $prefix The prefix to look for
 * @return string|array The prefixed name of the first microfomats class found or false
 */
function mfNamesFromClass($class, $prefix='h-') {
	$class = str_replace(array(' ', '	', "\n"), ' ', $class);
	$classes = explode(' ', $class);
	$classes = preg_grep('#^(h|p|u|dt|e)-([a-z0-9]+-)?[a-z]+(-[a-z]+)*$#', $classes);
	$matches = array();

	foreach ($classes as $classname) {
		$compare_classname = ' ' . $classname;
		$compare_prefix = ' ' . $prefix;
		if (strstr($compare_classname, $compare_prefix) !== false && ($compare_classname != $compare_prefix)) {
			$matches[] = ($prefix === 'h-') ? $classname : substr($classname, strlen($prefix));
		}
	}

	return $matches;
}

/**
 * Registered with the XPath object and used within XPaths for finding root elements.
 * @param string $class
 * @return bool
 */
function classHasMf2RootClassname($class) {
	return count(mfNamesFromClass($class, 'h-')) > 0;
}

/**
 * Get Nested µf Property Name From Class
 *
 * Returns all the p-, u-, dt- or e- prefixed classnames it finds in a
 * space-separated string.
 *
 * @param string $class
 * @return array
 */
function nestedMfPropertyNamesFromClass($class) {
	$prefixes = array('p-', 'u-', 'dt-', 'e-');
	$propertyNames = array();

	$class = str_replace(array(' ', '	', "\n"), ' ', $class);
	foreach (explode(' ', $class) as $classname) {
		foreach ($prefixes as $prefix) {
			// Check if $classname is a valid property classname for $prefix.
			if (substr($classname, 0, strlen($prefix)) == $prefix && $classname != $prefix) {
				$propertyName = substr($classname, strlen($prefix));
				$propertyNames[$propertyName][] = $prefix;
			}
		}
	}

	foreach ($propertyNames as $property => $prefixes) {
		$propertyNames[$property] = array_unique($prefixes);
	}

	return $propertyNames;
}

/**
 * Wraps mfNamesFromClass to handle an element as input (common)
 *
 * @param DOMElement $e The element to get the classname for
 * @param string $prefix The prefix to look for
 * @return mixed See return value of mf2\Parser::mfNameFromClass()
 */
function mfNamesFromElement(\DOMElement $e, $prefix = 'h-') {
	$class = $e->getAttribute('class');
	return mfNamesFromClass($class, $prefix);
}

/**
 * Wraps nestedMfPropertyNamesFromClass to handle an element as input
 */
function nestedMfPropertyNamesFromElement(\DOMElement $e) {
	$class = $e->getAttribute('class');
	return nestedMfPropertyNamesFromClass($class);
}

/**
 * Converts various time formats to HH:MM
 * @param string $time The time to convert
 * @return string
 */
function convertTimeFormat($time) {
	$hh = $mm = $ss = '';
	preg_match('/(\d{1,2}):?(\d{2})?:?(\d{2})?(a\.?m\.?|p\.?m\.?)?/i', $time, $matches);

	// If no am/pm is specified:
	if (empty($matches[4])) {
		return $time;
	} else {
		// Otherwise, am/pm is specified.
		$meridiem = strtolower(str_replace('.', '', $matches[4]));

		// Hours.
		$hh = $matches[1];

		// Add 12 to hours if pm applies.
		if ($meridiem == 'pm' && ($hh < 12)) {
			$hh += 12;
		}

		$hh = str_pad($hh, 2, '0', STR_PAD_LEFT);

		// Minutes.
		$mm = (empty($matches[2]) ) ? '00' : $matches[2];

		// Seconds, only if supplied.
		if (!empty($matches[3])) {
			$ss = $matches[3];
		}

		if (empty($ss)) {
			return sprintf('%s:%s', $hh, $mm);
		}
		else {
			return sprintf('%s:%s:%s', $hh, $mm, $ss);
		}
	}
}

/**
 * Normalize an ordinal date to YYYY-MM-DD
 * This function should only be called after validating the $dtValue
 * matches regex \d{4}-\d{2}
 * @param string $dtValue
 * @return string
 */
function normalizeOrdinalDate($dtValue) {
	list($year, $day) = explode('-', $dtValue, 2);
	$day = intval($day);
	if ($day < 367 && $day > 0) {
		$date = \DateTime::createFromFormat('Y-z', $dtValue);
		$date->modify('-1 day'); # 'z' format is zero-based so need to adjust
		if ($date->format('Y') === $year) {
			return $date->format('Y-m-d');
		}
	}
	return '';
}

/**
 * If a date value has a timezone offset, normalize it.
 * @param string $dtValue
 * @return string isolated, normalized TZ offset for implied TZ for other dt- properties
 */
function normalizeTimezoneOffset(&$dtValue) {
	preg_match('/Z|[+-]\d{1,2}:?(\d{2})?$/i', $dtValue, $matches);

	if (empty($matches)) {
		return null;
	}

	$timezoneOffset = null;

	if ( $matches[0] != 'Z' ) {
		$timezoneString = str_replace(':', '', $matches[0]);
		$plus_minus = substr($timezoneString, 0, 1);
		$timezoneOffset = substr($timezoneString, 1);
		if ( strlen($timezoneOffset) <= 2 ) {
			$timezoneOffset .= '00';
		}
		$timezoneOffset = str_pad($timezoneOffset, 4, 0, STR_PAD_LEFT);
		$timezoneOffset = $plus_minus . $timezoneOffset;
		$dtValue = preg_replace('/Z?[+-]\d{1,2}:?(\d{2})?$/i', $timezoneOffset, $dtValue);
	}

	return $timezoneOffset;
}

function applySrcsetUrlTransformation($srcset, $transformation) {
	return implode(', ', array_filter(array_map(function ($srcsetPart) use ($transformation) {
		$parts = explode(" \t\n\r\0\x0B", trim($srcsetPart), 2);
		$parts[0] = rtrim($parts[0]);

		if (empty($parts[0])) { return false; }

		$parts[0] = call_user_func($transformation, $parts[0]);

		return $parts[0] . (empty($parts[1]) ? '' : ' ' . $parts[1]);
	}, explode(',', trim($srcset)))));
}

/**
 * Microformats2 Parser
 *
 * A class which holds state for parsing microformats2 from HTML.
 *
 * Example usage:
 *
 *     use Mf2;
 *     $parser = new Mf2\Parser('<p class="h-card">Barnaby Walters</p>');
 *     $output = $parser->parse();
 */
class Parser {
	/** @var string The baseurl (if any) to use for this parse */
	public $baseurl;

	/** @var DOMXPath object which can be used to query over any fragment*/
	public $xpath;

	/** @var DOMDocument */
	public $doc;

	/** @var SplObjectStorage */
	protected $parsed;

	/**
	 * @var bool
	 */
	public $jsonMode;

	/** @var boolean Whether to include experimental language parsing in the result */
	public $lang = false;

	/** @var bool Whether to include alternates object (dropped from spec in favor of rel-urls) */
	public $enableAlternates = false;

	/**
	 * Elements upgraded to mf2 during backcompat
	 * @var SplObjectStorage
	 */
	protected $upgraded;

	/**
	 * Whether to convert classic microformats
	 * @var bool
	 */
	public $convertClassic;

	/**
	 * Constructor
	 *
	 * @param DOMDocument|string $input The data to parse. A string of HTML or a DOMDocument
	 * @param string $url The URL of the parsed document, for relative URL resolution
	 * @param boolean $jsonMode Whether or not to use a stdClass instance for an empty `rels` dictionary. This breaks PHP looping over rels, but allows the output to be correctly serialized as JSON.
	 */
	public function __construct($input, $url = null, $jsonMode = false) {
		$emptyDocDefault = '<html><body></body></html>';
		libxml_use_internal_errors(true);
		if (is_string($input)) {
			if (empty($input)) {
					$input = $emptyDocDefault;
			}
				
			if (class_exists('Masterminds\\HTML5')) {
					$doc = new \Masterminds\HTML5(array('disable_html_ns' => true));
					$doc = $doc->loadHTML($input);
			} else {
				$doc = load_dom_document($input);
			}
		} elseif (is_a($input, 'DOMDocument')) {
			$doc = clone $input;
		} else {
			$doc = new DOMDocument();
			@$doc->loadHTML($emptyDocDefault);
		}

		// Create an XPath object and allow some PHP functions to be used within XPath queries.
		$this->xpath = new DOMXPath($doc);
		$this->xpath->registerNamespace('php', 'http://php.net/xpath');
		$this->xpath->registerPhpFunctions('\\Mf2\\classHasMf2RootClassname');

		$baseurl = $url;
		foreach ($this->xpath->query('//base[@href]') as $base) {
			$baseElementUrl = $base->getAttribute('href');

			if (parse_url($baseElementUrl, PHP_URL_SCHEME) === null) {
				/* The base element URL is relative to the document URL.
				 *
				 * :/
				 *
				 * Perhaps the author was high? */

				$baseurl = resolveUrl($url, $baseElementUrl);
			} else {
				$baseurl = $baseElementUrl;
			}
			break;
		}

		// Ignore <template> elements as per the HTML5 spec
		foreach ($this->xpath->query('//template') as $templateEl) {
			$templateEl->parentNode->removeChild($templateEl);
		}

		$this->baseurl = $baseurl;
		$this->doc = $doc;
		$this->parsed = new SplObjectStorage();
		$this->upgraded = new SplObjectStorage();
		$this->jsonMode = $jsonMode;
	}

	private function elementPrefixParsed(\DOMElement $e, $prefix) {
		if (!$this->parsed->contains($e))
			$this->parsed->attach($e, array());

		$prefixes = $this->parsed[$e];
		$prefixes[] = $prefix;
		$this->parsed[$e] = $prefixes;
	}

	/**
	 * Determine if the element has already been parsed
	 * @param DOMElement $e
	 * @param string $prefix
	 * @return bool
	 */
	private function isElementParsed(\DOMElement $e, $prefix) {
		if (!$this->parsed->contains($e)) {
			return false;
		}

		$prefixes = $this->parsed[$e];

		if (!in_array($prefix, $prefixes)) {
			return false;
		}

		return true;
	}

	/**
	 * Determine if the element's specified property has already been upgraded during backcompat
	 * @param DOMElement $el
	 * @param string $property
	 * @return bool
	 */
	private function isElementUpgraded(\DOMElement $el, $property) {
		if ( $this->upgraded->contains($el) ) {
			if ( in_array($property, $this->upgraded[$el]) ) {
				return true;
			}
		}

		return false;
	}

	private function resolveChildUrls(DOMElement $el) {
		$hyperlinkChildren = $this->xpath->query('.//*[@src or @href or @data]', $el);

		foreach ($hyperlinkChildren as $child) {
			if ($child->hasAttribute('href'))
				$child->setAttribute('href', $this->resolveUrl($child->getAttribute('href')));
			if ($child->hasAttribute('src'))
				$child->setAttribute('src', $this->resolveUrl($child->getAttribute('src')));
			if ($child->hasAttribute('srcset'))
				$child->setAttribute('srcset', applySrcsetUrlTransformation($child->getAttribute('href'), array($this, 'resolveUrl')));
			if ($child->hasAttribute('data'))
				$child->setAttribute('data', $this->resolveUrl($child->getAttribute('data')));
		}
	}

	/**
	 * The following two methods implements plain text parsing.
	 * @param DOMElement $element
	 * @param bool $implied
	 * @see https://wiki.zegnat.net/media/textparsing.html
	 **/
	public function textContent(DOMElement $element, $implied=false)
	{
				return preg_replace(
						'/(^[\t\n\f\r ]+| +(?=\n)|(?<=\n) +| +(?= )|[\t\n\f\r ]+$)/',
						'',
						$this->elementToString($element, $implied)
				);
	}
	private function elementToString(DOMElement $input, $implied=false)
	{
			$output = '';
			foreach ($input->childNodes as $child) {
					if ($child->nodeType === XML_TEXT_NODE) {
							$output .= str_replace(array("\t", "\n", "\r") , ' ', $child->textContent);
					} else if ($child->nodeType === XML_ELEMENT_NODE) {
							$tagName = strtoupper($child->tagName);
							if (in_array($tagName, array('SCRIPT', 'STYLE'))) {
									continue;
							} else if ($tagName === 'IMG') {
									if ($child->hasAttribute('alt')) {
											$output .= ' ' . trim($child->getAttribute('alt'), "\t\n\f\r ") . ' ';
									} else if (!$implied && $child->hasAttribute('src')) {
											$output .= ' ' . $this->resolveUrl(trim($child->getAttribute('src'), "\t\n\f\r ")) . ' ';
									}
							} else if ($tagName === 'BR') {
									$output .= "\n";
							} else if ($tagName === 'P') {
									$output .= "\n" . $this->elementToString($child);
							} else {
									$output .= $this->elementToString($child);
							}
					}
			}
			return $output;
	}

	/**
	 * Given an img property, parse its value and/or alt text
	 * @param DOMElement $el
	 * @access public
	 * @return string|array
	 */
	public function parseImg(DOMElement $el)
	{
		if ($el->hasAttribute('alt')) {
			return [
				'value' => $this->resolveUrl( $el->getAttribute('src') ),
				'alt' => $el->getAttribute('alt')
			];
		}
		return $el->getAttribute('src');
	}
	/**
	 * This method parses the language of an element
	 * @param DOMElement $el
	 * @access public
	 * @return string
	 */
	public function language(DOMElement $el)
	{
		// element has a lang attribute; use it
		if ($el->hasAttribute('lang')) {
			return unicodeTrim($el->getAttribute('lang'));
		}

		if ($el->tagName == 'html') {
			// we're at the <html> element and no lang; check <meta> http-equiv Content-Language
			foreach ( $this->xpath->query('.//meta[@http-equiv]') as $node )
			{
				if ($node->hasAttribute('http-equiv') && $node->hasAttribute('content') && strtolower($node->getAttribute('http-equiv')) == 'content-language') {
					return unicodeTrim($node->getAttribute('content'));
				}
			}
		} elseif ($el->parentNode instanceof DOMElement) {
			// check the parent node
			return $this->language($el->parentNode);
		}

		return '';
	} # end method language()

	// TODO: figure out if this has problems with sms: and geo: URLs
	public function resolveUrl($url) {
		// If not a string then return.
		if (!is_string($url)){
			return $url;
		}
		// If the URL is seriously malformed it’s probably beyond the scope of this
		// parser to try to do anything with it.
		if (parse_url($url) === false) {
			return $url;
		}

		// per issue #40 valid URLs could have a space on either side
		$url = trim($url);

		$scheme = parse_url($url, PHP_URL_SCHEME);

		if (empty($scheme) and !empty($this->baseurl)) {
			return resolveUrl($this->baseurl, $url);
		} else {
			return $url;
		}
	}

	// Parsing Functions

	/**
	 * Parse value-class/value-title on an element, joining with $separator if
	 * there are multiple.
	 *
	 * @param \DOMElement $e
	 * @param string $separator = '' if multiple value-title elements, join with this string
	 * @return string|null the parsed value or null if value-class or -title aren’t in use
	 */
	public function parseValueClassTitle(\DOMElement $e, $separator = '') {
		$valueClassElements = $this->xpath->query('./*[contains(concat(" ", normalize-space(@class), " "), " value ")]', $e);

		if ($valueClassElements->length !== 0) {
			// Process value-class stuff
			$val = '';
			foreach ($valueClassElements as $el) {
				$val .= $this->textContent($el);
			}

			return unicodeTrim($val);
		}

		$valueTitleElements = $this->xpath->query('./*[contains(concat(" ", normalize-space(@class), " "), " value-title ")]', $e);

		if ($valueTitleElements->length !== 0) {
			// Process value-title stuff
			$val = '';
			foreach ($valueTitleElements as $el) {
				$val .= $el->getAttribute('title');
			}

			return unicodeTrim($val);
		}

		// No value-title or -class in this element
		return null;
	}

	/**
	 * Given an element with class="p-*", get its value
	 *
	 * @param DOMElement $p The element to parse
	 * @return string The plaintext value of $p, dependant on type
	 * @todo Make this adhere to value-class
	 */
	public function parseP(\DOMElement $p) {
		$classTitle = $this->parseValueClassTitle($p, ' ');

		if ($classTitle !== null) {
			return $classTitle;
		}

		$this->resolveChildUrls($p);

		if ($p->tagName == 'img' and $p->hasAttribute('alt')) {
			$pValue = $p->getAttribute('alt');
		} elseif ($p->tagName == 'area' and $p->hasAttribute('alt')) {
			$pValue = $p->getAttribute('alt');
		} elseif (($p->tagName == 'abbr' or $p->tagName == 'link') and $p->hasAttribute('title')) {
			$pValue = $p->getAttribute('title');
		} elseif (in_array($p->tagName, array('data', 'input')) and $p->hasAttribute('value')) {
			$pValue = $p->getAttribute('value');
		} else {
			$pValue = $this->textContent($p);
		}

		return $pValue;
	}

	/**
	 * Given an element with class="u-*", get the value of the URL
	 *
	 * @param DOMElement $u The element to parse
	 * @return string The plaintext value of $u, dependant on type
	 * @todo make this adhere to value-class
	 */
	public function parseU(\DOMElement $u) {
		if (($u->tagName == 'a' or $u->tagName == 'area' or $u->tagName == 'link') and $u->hasAttribute('href')) {
			$uValue = $u->getAttribute('href');
		} elseif ( $u->tagName == 'img' and $u->hasAttribute('src') ) {
			$uValue = $this->parseImg($u);
		} elseif (in_array($u->tagName, array('audio', 'video', 'source', 'iframe')) and $u->hasAttribute('src')) {
			$uValue = $u->getAttribute('src');
		} elseif ($u->tagName == 'video' and !$u->hasAttribute('src') and $u->hasAttribute('poster')) {
			$uValue = $u->getAttribute('poster');
		} elseif ($u->tagName == 'object' and $u->hasAttribute('data')) {
			$uValue = $u->getAttribute('data');
		} elseif (($classTitle = $this->parseValueClassTitle($u)) !== null) {
				$uValue = $classTitle;
		} elseif (($u->tagName == 'abbr' or $u->tagName == 'link') and $u->hasAttribute('title')) {
			$uValue = $u->getAttribute('title');
		} elseif (in_array($u->tagName, array('data', 'input')) and $u->hasAttribute('value')) {
			$uValue = $u->getAttribute('value');
		} else {
			$uValue = $this->textContent($u);
		}

		return $this->resolveUrl($uValue);
	}

	/**
	 * Given an element with class="dt-*", get the value of the datetime as a php date object
	 *
	 * @param DOMElement $dt The element to parse
	 * @param array $dates Array of dates processed so far
	 * @param string $impliedTimezone
	 * @return string The datetime string found
	 */
	public function parseDT(\DOMElement $dt, &$dates = array(), &$impliedTimezone = null) {
		// Check for value-class pattern
		$valueClassChildren = $this->xpath->query('./*[contains(concat(" ", normalize-space(@class), " "), " value ") or contains(concat(" ", normalize-space(@class), " "), " value-title ")]', $dt);
		$dtValue = false;

		if ($valueClassChildren->length > 0) {
			// They’re using value-class
			$dateParts = array();

			foreach ($valueClassChildren as $e) {
				if (strstr(' ' . $e->getAttribute('class') . ' ', ' value-title ')) {
					$title = $e->getAttribute('title');
					if (!empty($title)) {
						$dateParts[] = $title;
					}
				}
				elseif ($e->tagName == 'img' or $e->tagName == 'area') {
					// Use @alt
					$alt = $e->getAttribute('alt');
					if (!empty($alt)) {
						$dateParts[] = $alt;
					}
				}
				elseif ($e->tagName == 'data') {
					// Use @value, otherwise innertext
					$value = $e->hasAttribute('value') ? $e->getAttribute('value') : unicodeTrim($e->nodeValue);
					if (!empty($value)) {
						$dateParts[] = $value;
					}
				}
				elseif ($e->tagName == 'abbr') {
					// Use @title, otherwise innertext
					$title = $e->hasAttribute('title') ? $e->getAttribute('title') : unicodeTrim($e->nodeValue);
					if (!empty($title)) {
						$dateParts[] = $title;
					}
				}
				elseif ($e->tagName == 'del' or $e->tagName == 'ins' or $e->tagName == 'time') {
					// Use @datetime if available, otherwise innertext
					$dtAttr = ($e->hasAttribute('datetime')) ? $e->getAttribute('datetime') : unicodeTrim($e->nodeValue);
					if (!empty($dtAttr)) {
						$dateParts[] = $dtAttr;
					}
				}
				else {
					if (!empty($e->nodeValue)) {
						$dateParts[] = unicodeTrim($e->nodeValue);
					}
				}
			}

			// Look through dateParts
			$datePart = '';
			$timePart = '';
			$timezonePart = '';
			foreach ($dateParts as $part) {
				// Is this part a full ISO8601 datetime?
				if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?(Z|[+-]\d{2}:?\d{2})?$/', $part)) {
					// Break completely, we’ve got our value.
					$dtValue = $part;
					break;
				} else {
					// Is the current part a valid time(+TZ?) AND no other time representation has been found?
					if ((preg_match('/^\d{1,2}:\d{2}(:\d{2})?(Z|[+-]\d{1,2}:?\d{2})?$/', $part) or preg_match('/^\d{1,2}(:\d{2})?(:\d{2})?[ap]\.?m\.?$/i', $part)) and empty($timePart)) {
						$timePart = $part;

						$timezoneOffset = normalizeTimezoneOffset($timePart);
						if (!$impliedTimezone && $timezoneOffset) {
							$impliedTimezone = $timezoneOffset;
						}
					// Is the current part a valid date AND no other date representation has been found?
					} elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $part) and empty($datePart)) {
						$datePart = $part;
					// Is the current part a valid ordinal date AND no other date representation has been found?
					} elseif (preg_match('/^\d{4}-\d{3}$/', $part) and empty($datePart)) {
						$datePart = normalizeOrdinalDate($part);
					// Is the current part a valid timezone offset AND no other timezone part has been found?
					} elseif (preg_match('/^(Z|[+-]\d{1,2}:?(\d{2})?)$/', $part) and empty($timezonePart)) {
						$timezonePart = $part;

						$timezoneOffset = normalizeTimezoneOffset($timezonePart);
						if (!$impliedTimezone && $timezoneOffset) {
							$impliedTimezone = $timezoneOffset;
						}
					// Current part already represented by other VCP parts; do nothing with it
					} else {
						continue;
					}

					if ( !empty($datePart) && !in_array($datePart, $dates) ) {
						$dates[] = $datePart;
					}

					if (!empty($timezonePart) && !empty($timePart)) {
						$timePart .= $timezonePart;
					}

					$dtValue = '';

					if ( empty($datePart) && !empty($timePart) ) {
						$timePart = convertTimeFormat($timePart);
						$dtValue = unicodeTrim($timePart);
					}
					else if ( !empty($datePart) && empty($timePart) ) {
						$dtValue = rtrim($datePart, 'T');
					}
					else {
						$timePart = convertTimeFormat($timePart);
						$dtValue = rtrim($datePart, 'T') . ' ' . unicodeTrim($timePart);
					}
				}
			}
		} else {
			// Not using value-class (phew).
			if ($dt->tagName == 'img' or $dt->tagName == 'area') {
				// Use @alt
				// Is it an entire dt?
				$alt = $dt->getAttribute('alt');
				if (!empty($alt)) {
					$dtValue = $alt;
				}
			} elseif (in_array($dt->tagName, array('data'))) {
				// Use @value, otherwise innertext
				// Is it an entire dt?
				$value = $dt->getAttribute('value');
				if (!empty($value)) {
					$dtValue = $value;
				}
				else {
					$dtValue = $this->textContent($dt);
				}
			} elseif ($dt->tagName == 'abbr') {
				// Use @title, otherwise innertext
				// Is it an entire dt?
				$title = $dt->getAttribute('title');
				if (!empty($title)) {
					$dtValue = $title;
				}
				else {
					$dtValue = $this->textContent($dt);
				}
			} elseif ($dt->tagName == 'del' or $dt->tagName == 'ins' or $dt->tagName == 'time') {
				// Use @datetime if available, otherwise innertext
				// Is it an entire dt?
				$dtAttr = $dt->getAttribute('datetime');
				if (!empty($dtAttr)) {
					$dtValue = $dtAttr;
				}
				else {
					$dtValue = $this->textContent($dt);
				}

			} else {
				$dtValue = $this->textContent($dt);
			}

			// if the dtValue is not just YYYY-MM-DD
			if (!preg_match('/^(\d{4}-\d{2}-\d{2})$/', $dtValue)) {
				// no implied timezone set and dtValue has a TZ offset, use un-normalized TZ offset
				preg_match('/Z|[+-]\d{1,2}:?(\d{2})?$/i', $dtValue, $matches);
				if (!$impliedTimezone && !empty($matches[0])) {
					$impliedTimezone = $matches[0];
				}
			}

			$dtValue = unicodeTrim($dtValue);

			// Store the date part so that we can use it when assembling the final timestamp if the next one is missing a date part
			if (preg_match('/(\d{4}-\d{2}-\d{2})/', $dtValue, $matches)) {
				$dates[] = $matches[0];
			}
		}

		/**
		 * if $dtValue is only a time and there are recently parsed dates,
		 * form the full date-time using the most recently parsed dt- value
		 */
		if ((preg_match('/^\d{1,2}:\d{2}(:\d{2})?(Z|[+-]\d{2}:?\d{2}?)?$/', $dtValue) or preg_match('/^\d{1,2}(:\d{2})?(:\d{2})?[ap]\.?m\.?$/i', $dtValue)) && !empty($dates)) {
			$timezoneOffset = normalizeTimezoneOffset($dtValue);
			if (!$impliedTimezone && $timezoneOffset) {
				$impliedTimezone = $timezoneOffset;
			}

			$dtValue = convertTimeFormat($dtValue);
			$dtValue = end($dates) . ' ' . unicodeTrim($dtValue);
		}

		return $dtValue;
	}

	/**
	 * 	Given the root element of some embedded markup, return a string representing that markup
	 *
	 * 	@param DOMElement $e The element to parse
	 * 	@return string $e’s innerHTML
	 *
	 * @todo need to mark this element as e- parsed so it doesn’t get parsed as it’s parent’s e-* too
	 */
	public function parseE(\DOMElement $e) {
		$classTitle = $this->parseValueClassTitle($e);

		if ($classTitle !== null)
			return $classTitle;

		// Expand relative URLs within children of this element
		// TODO: as it is this is not relative to only children, make this .// and rerun tests
		$this->resolveChildUrls($e);

		// Temporarily move all descendants into a separate DocumentFragment.
		// This way we can DOMDocument::saveHTML on the entire collection at once.
		// Running DOMDocument::saveHTML per node may add whitespace that isn't in source.
		// See https://stackoverflow.com/q/38317903
		if ($innerNodes = $e->ownerDocument->createDocumentFragment()) {
			while ($e->hasChildNodes()) {
				$innerNodes->appendChild($e->firstChild);
			}
			$html = $e->ownerDocument->saveHtml($innerNodes);
			// Put the nodes back in place.
			if ($innerNodes->hasChildNodes()) {
				$e->appendChild($innerNodes);
			}
		}

		$return = array(
			'html' => unicodeTrim($html),
			'value' => $this->textContent($e),
		);

		if($this->lang) {
			// Language
			if ( $html_lang = $this->language($e) ) {
				$return['lang'] = $html_lang;
			}
		}

		return $return;
	}

	private function removeTags(\DOMElement &$e, $tagName) {
		while(($r = $e->getElementsByTagName($tagName)) && $r->length) {
			$r->item(0)->parentNode->removeChild($r->item(0));
		}
	}

	/**
	 * Recursively parse microformats
	 *
	 * @param DOMElement $e The element to parse
	 * @param bool $is_backcompat Whether using backcompat parsing or not
	 * @param bool $has_nested_mf Whether this microformat has a nested microformat
	 * @return array A representation of the values contained within microformat $e
	 */
	public function parseH(\DOMElement $e, $is_backcompat = false, $has_nested_mf = false) {
		// If it’s already been parsed (e.g. is a child mf), skip
		if ($this->parsed->contains($e)) {
			return null;
		}

		// Get current µf name
		$mfTypes = mfNamesFromElement($e, 'h-');

		if (!$mfTypes) {
			return null;
		}

		// Initalise var to store the representation in
		$return = array();
		$children = array();
		$dates = array();
		$prefixes = array();
		$impliedTimezone = null;

		if($e->tagName == 'area') {
			$coords = $e->getAttribute('coords');
			$shape = $e->getAttribute('shape');
		}

		// Handle p-*
		foreach ($this->xpath->query('.//*[contains(concat(" ", normalize-space(@class)) ," p-")]', $e) as $p) {
			// element is already parsed
			if ($this->isElementParsed($p, 'p')) {
				continue;
			// backcompat parsing and element was not upgraded; skip it
			} else if ( $is_backcompat && empty($this->upgraded[$p]) ) {
				$this->elementPrefixParsed($p, 'p');
				continue;
			}

			$prefixes[] = 'p-';
			$pValue = $this->parseP($p);

			// Add the value to the array for it’s p- properties
			foreach (mfNamesFromElement($p, 'p-') as $propName) {
				if (!empty($propName)) {
					$return[$propName][] = $pValue;
				}
			}

			// Make sure this sub-mf won’t get parsed as a top level mf
			$this->elementPrefixParsed($p, 'p');
		}

		// Handle u-*
		foreach ($this->xpath->query('.//*[contains(concat(" ", normalize-space(@class))," u-")]', $e) as $u) {
			// element is already parsed
			if ($this->isElementParsed($u, 'u')) {
				continue;
			// backcompat parsing and element was not upgraded; skip it
			} else if ( $is_backcompat && empty($this->upgraded[$u]) ) {
				$this->elementPrefixParsed($u, 'u');
				continue;
			}

			$prefixes[] = 'u-';
			$uValue = $this->parseU($u);

			// Add the value to the array for it’s property types
			foreach (mfNamesFromElement($u, 'u-') as $propName) {
				$return[$propName][] = $uValue;
			}

			// Make sure this sub-mf won’t get parsed as a top level mf
			$this->elementPrefixParsed($u, 'u');
		}

		$temp_dates = array();

		// Handle dt-*
		foreach ($this->xpath->query('.//*[contains(concat(" ", normalize-space(@class)), " dt-")]', $e) as $dt) {
			// element is already parsed
			if ($this->isElementParsed($dt, 'dt')) {
				continue;
			// backcompat parsing and element was not upgraded; skip it
			} else if ( $is_backcompat && empty($this->upgraded[$dt]) ) {
				$this->elementPrefixParsed($dt, 'dt');
				continue;
			}

			$prefixes[] = 'dt-';
			$dtValue = $this->parseDT($dt, $dates, $impliedTimezone);

			if ($dtValue) {
				// Add the value to the array for dt- properties
				foreach (mfNamesFromElement($dt, 'dt-') as $propName) {
					$temp_dates[$propName][] = $dtValue;
				}
			}
			// Make sure this sub-mf won’t get parsed as a top level mf
			$this->elementPrefixParsed($dt, 'dt');
		}

		foreach ($temp_dates as $propName => $data) {
			foreach ( $data as $dtValue ) {
				// var_dump(preg_match('/[+-]\d{2}(\d{2})?$/i', $dtValue));
				if ( $impliedTimezone && preg_match('/(Z|[+-]\d{2}:?(\d{2})?)$/i', $dtValue, $matches) == 0 ) {
					$dtValue .= $impliedTimezone;
				}

				$return[$propName][] = $dtValue;
			}
		}

		// Handle e-*
		foreach ($this->xpath->query('.//*[contains(concat(" ", normalize-space(@class))," e-")]', $e) as $em) {
			// element is already parsed
			if ($this->isElementParsed($em, 'e')) {
				continue;
			// backcompat parsing and element was not upgraded; skip it
			} else if ( $is_backcompat && empty($this->upgraded[$em]) ) {
				$this->elementPrefixParsed($em, 'e');
				continue;
			}

			$prefixes[] = 'e-';
			$eValue = $this->parseE($em);

			if ($eValue) {
				// Add the value to the array for e- properties
				foreach (mfNamesFromElement($em, 'e-') as $propName) {
					$return[$propName][] = $eValue;
				}
			}
			// Make sure this sub-mf won’t get parsed as a top level mf
			$this->elementPrefixParsed($em, 'e');
		}

		// Do we need to imply a name property?
		// if no explicit "name" property, and no other p-* or e-* properties, and no nested microformats,
		if (!array_key_exists('name', $return) && !in_array('p-', $prefixes)
			&& !in_array('e-', $prefixes) && !$has_nested_mf
			&& !$is_backcompat && empty($this->upgraded[$e])) {
			$name = false;
			// img.h-x[alt] or area.h-x[alt]
			if (($e->tagName === 'img' || $e->tagName === 'area') && $e->hasAttribute('alt')) {
				$name = $e->getAttribute('alt');
			// abbr.h-x[title]
			} elseif ($e->tagName === 'abbr' && $e->hasAttribute('title')) {
				$name = $e->getAttribute('title');
			} else {
				$xpaths = array(
					// .h-x>img:only-child[alt]:not([alt=""]):not[.h-*]
					'./img[not(contains(concat(" ", @class), " h-")) and count(../*) = 1 and @alt and string-length(@alt) != 0]',
					// .h-x>area:only-child[alt]:not([alt=""]):not[.h-*]
					'./area[not(contains(concat(" ", @class), " h-")) and count(../*) = 1 and @alt and string-length(@alt) != 0]',
					// .h-x>abbr:only-child[title]:not([title=""]):not[.h-*]
					'./abbr[not(contains(concat(" ", @class), " h-")) and count(../*) = 1 and @title and string-length(@title) != 0]',
					// .h-x>:only-child:not[.h-*]>img:only-child[alt]:not([alt=""]):not[.h-*]
					'./*[not(contains(concat(" ", @class), " h-")) and count(../*) = 1 and count(*) = 1]/img[not(contains(concat(" ", @class), " h-")) and @alt and string-length(@alt) != 0]',
					// .h-x>:only-child:not[.h-*]>area:only-child[alt]:not([alt=""]):not[.h-*]
					'./*[not(contains(concat(" ", @class), " h-")) and count(../*) = 1 and count(*) = 1]/area[not(contains(concat(" ", @class), " h-")) and @alt and string-length(@alt) != 0]',
					// .h-x>:only-child:not[.h-*]>abbr:only-child[title]:not([title=""]):not[.h-*]
					'./*[not(contains(concat(" ", @class), " h-")) and count(../*) = 1 and count(*) = 1]/abbr[not(contains(concat(" ", @class), " h-")) and @title and string-length(@title) != 0]'
				);
				foreach ($xpaths as $xpath) {
					$nameElement = $this->xpath->query($xpath, $e);
					if ($nameElement !== false && $nameElement->length === 1) {
						$nameElement = $nameElement->item(0);
						if ($nameElement->tagName === 'img' || $nameElement->tagName === 'area') {
							$name = $nameElement->getAttribute('alt');
						} else {
							$name = $nameElement->getAttribute('title');
						}
						break;
					}
				}
			}
			if ($name === false) {
				$name = $this->textContent($e, true);
			}
			$return['name'][] = unicodeTrim($name);
		}

		// Check for u-photo
		if (!array_key_exists('photo', $return) && !in_array('u-', $prefixes) && !$has_nested_mf && !$is_backcompat) {
			$photo = $this->parseImpliedPhoto($e);
			if ($photo !== false) {
				$return['photo'][] = $photo;
			}
		}

		// Do we need to imply a url property?
		// if no explicit "url" property, and no other explicit u-* properties, and no nested microformats
		if (!array_key_exists('url', $return) && !in_array('u-', $prefixes) && !$has_nested_mf && !$is_backcompat) {
			// a.h-x[href] or area.h-x[href]
			if (($e->tagName === 'a' || $e->tagName === 'area') && $e->hasAttribute('href')) {
				$return['url'][] = $this->resolveUrl($e->getAttribute('href'));
			} else {
				$xpaths = array(
					// .h-x>a[href]:only-of-type:not[.h-*]
					'./a[not(contains(concat(" ", @class), " h-")) and count(../a) = 1 and @href]',
					// .h-x>area[href]:only-of-type:not[.h-*]
					'./area[not(contains(concat(" ", @class), " h-")) and count(../area) = 1 and @href]',
					// .h-x>:only-child:not[.h-*]>a[href]:only-of-type:not[.h-*]
					'./*[not(contains(concat(" ", @class), " h-")) and count(../*) = 1 and count(a) = 1]/a[not(contains(concat(" ", @class), " h-")) and @href]',
					// .h-x>:only-child:not[.h-*]>area[href]:only-of-type:not[.h-*]
					'./*[not(contains(concat(" ", @class), " h-")) and count(../*) = 1 and count(area) = 1]/area[not(contains(concat(" ", @class), " h-")) and @href]'
				);
				foreach ($xpaths as $xpath) {
					$url = $this->xpath->query($xpath, $e);
					if ($url !== false && $url->length === 1) {
						$return['url'][] = $this->resolveUrl($url->item(0)->getAttribute('href'));
						break;
					}
				}
			}
		}

		// Make sure things are unique and in alphabetical order
		$mfTypes = array_unique($mfTypes);
		sort($mfTypes);

		// Properties should be an object when JSON serialised
		if (empty($return) and $this->jsonMode) {
			$return = new stdClass();
		}

		// Phew. Return the final result.
		$parsed = array(
			'type' => $mfTypes,
			'properties' => $return
		);

		if(trim($e->getAttribute('id')) !== '') {
			$parsed['id'] = trim($e->getAttribute("id"));
		}

		if($this->lang) {
			// Language
			if ( $html_lang = $this->language($e) ) {
				$parsed['lang'] = $html_lang;
			}
		}

		if (!empty($shape)) {
			$parsed['shape'] = $shape;
		}

		if (!empty($coords)) {
			$parsed['coords'] = $coords;
		}

		if (!empty($children)) {
			$parsed['children'] = array_values(array_filter($children));
		}
		return $parsed;
	}

	/**
	 * @see http://microformats.org/wiki/microformats2-parsing#parsing_for_implied_properties
	 */
	public function parseImpliedPhoto(\DOMElement $e) {

		// img.h-x[src]
		if ($e->tagName == 'img') {
			return $this->resolveUrl($this->parseImg($e));
		}

		// object.h-x[data]
		if ($e->tagName == 'object' && $e->hasAttribute('data')) {
			return $this->resolveUrl($e->getAttribute('data'));
		}

		$xpaths = array(
			// .h-x>img[src]:only-of-type:not[.h-*]
			'./img[not(contains(concat(" ", @class), " h-")) and count(../img) = 1 and @src]',
			// .h-x>object[data]:only-of-type:not[.h-*]
			'./object[not(contains(concat(" ", @class), " h-")) and count(../object) = 1 and @data]',
			// .h-x>:only-child:not[.h-*]>img[src]:only-of-type:not[.h-*]
			'./*[not(contains(concat(" ", @class), " h-")) and count(../*) = 1 and count(img) = 1]/img[not(contains(concat(" ", @class), " h-")) and @src]',
			// .h-x>:only-child:not[.h-*]>object[data]:only-of-type:not[.h-*]
			'./*[not(contains(concat(" ", @class), " h-")) and count(../*) = 1 and count(object) = 1]/object[not(contains(concat(" ", @class), " h-")) and @data]',
		);

		foreach ($xpaths as $path) {
			$els = $this->xpath->query($path, $e);

			if ($els !== false && $els->length === 1) {
				$el = $els->item(0);
				if ($el->tagName == 'img') {
					$return = $this->parseImg($el);
					return $this->resolveUrl($return);
				} else if ($el->tagName == 'object') {
					return $this->resolveUrl($el->getAttribute('data'));
				}
			}
		}

		// no implied photo
		return false;
	}

	/**
	 * Parse rels and alternates
	 *
	 * Returns [$rels, $rel_urls, $alternates].
	 * For $rels and $rel_urls, if they are empty and $this->jsonMode = true, they will be returned as stdClass,
	 * optimizing for JSON serialization. Otherwise they will be returned as an empty array.
	 * Note that $alternates is deprecated in the microformats spec in favor of $rel_urls. $alternates only appears
	 * in parsed results if $this->enableAlternates = true.
	 * @return array|stdClass
	 */
	public function parseRelsAndAlternates() {
		$rels = array();
		$rel_urls = array();
		$alternates = array();

		// Iterate through all a, area and link elements with rel attributes
		foreach ($this->xpath->query('//a[@rel and @href] | //link[@rel and @href] | //area[@rel and @href]') as $hyperlink) {
			// Parse the set of rels for the current link
			$linkRels = array_unique(array_filter(preg_split('/[\t\n\f\r ]/', $hyperlink->getAttribute('rel'))));
			if (count($linkRels) === 0) {
				continue;
			}

			// Resolve the href
			$href = $this->resolveUrl($hyperlink->getAttribute('href'));

			$rel_attributes = array();

			if ($hyperlink->hasAttribute('media')) {
				$rel_attributes['media'] = $hyperlink->getAttribute('media');
			}

			if ($hyperlink->hasAttribute('hreflang')) {
				$rel_attributes['hreflang'] = $hyperlink->getAttribute('hreflang');
			}

			if ($hyperlink->hasAttribute('title')) {
				$rel_attributes['title'] = $hyperlink->getAttribute('title');
			}

			if ($hyperlink->hasAttribute('type')) {
				$rel_attributes['type'] = $hyperlink->getAttribute('type');
			}

			if (strlen($hyperlink->textContent) > 0) {
				$rel_attributes['text'] = $hyperlink->textContent;
			}

			if ($this->enableAlternates) {
				// If 'alternate' in rels, create 'alternates' structure, append
				if (in_array('alternate', $linkRels)) {
					$alternates[] = array_merge(
						$rel_attributes,
						array(
							'url' => $href,
							'rel' => implode(' ', array_diff($linkRels, array('alternate')))
						)
					);
				}
			}

			foreach ($linkRels as $rel) {
				if (!array_key_exists($rel, $rels)) {
					$rels[$rel] = array($href);
				} elseif (!in_array($href, $rels[$rel])) {
					$rels[$rel][] = $href;
				}
			}

			if (!array_key_exists($href, $rel_urls)) {
				$rel_urls[$href] = array('rels' => array());
			}

			// Add the attributes collected only if they were not already set
			$rel_urls[$href] = array_merge(
				$rel_attributes,
				$rel_urls[$href]
			);

			// Merge current rels with those already set
			$rel_urls[$href]['rels'] = array_merge(
				$rel_urls[$href]['rels'],
				$linkRels
			);
		}

		// Alphabetically sort the rels arrays after removing duplicates
		foreach ($rel_urls as $href => $object) {
			$rel_urls[$href]['rels'] = array_unique($rel_urls[$href]['rels']);
			sort($rel_urls[$href]['rels']);
		}

		if (empty($rels) and $this->jsonMode) {
			$rels = new stdClass();
		}

		if (empty($rel_urls) and $this->jsonMode) {
			$rel_urls = new stdClass();
		}

		return array($rels, $rel_urls, $alternates);
	}

	/**
	 * Find rel=tag elements that don't have class=category and have an href.
	 * For each element, get the last non-empty URL segment. Append a <data>
	 * element with that value as the category. Uses the mf1 class 'category'
	 * which will then be upgraded to p-category during backcompat.
	 * @param DOMElement $el
	 */
	public function upgradeRelTagToCategory(DOMElement $el) {
		$rel_tag = $this->xpath->query('.//a[contains(concat(" ",normalize-space(@rel)," ")," tag ") and not(contains(concat(" ", normalize-space(@class), " "), " category ")) and @href]', $el);

		if ( $rel_tag->length ) {
			foreach ( $rel_tag as $tempEl ) {
				$path = trim(parse_url($tempEl->getAttribute('href'), PHP_URL_PATH), ' /');
				$segments = explode('/', $path);
				$value = array_pop($segments);

				# build the <data> element
				$dataEl = $tempEl->ownerDocument->createElement('data');
				$dataEl->setAttribute('class', 'category');
				$dataEl->setAttribute('value', $value);

				# append as child of input element. this should ensure added element does get parsed inside e-*
				$el->appendChild($dataEl);
			}
		}
	}

	/**
	 * Kicks off the parsing routine
	 * @param bool $convertClassic whether to do backcompat parsing on microformats1. Defaults to true.
	 * @param DOMElement $context optionally specify an element from which to parse microformats
	 * @return array An array containing all the microformats found in the current document
	 */
	public function parse($convertClassic = true, DOMElement $context = null) {
		$this->convertClassic = $convertClassic;
		$mfs = $this->parse_recursive($context);

		// Parse rels
		list($rels, $rel_urls, $alternates) = $this->parseRelsAndAlternates();

		$top = array(
			'items' => array_values(array_filter($mfs)),
			'rels' => $rels,
			'rel-urls' => $rel_urls,
		);

		if ($this->enableAlternates && count($alternates)) {
			$top['alternates'] = $alternates;
		}

		return $top;
	}


	/**
	 * Parse microformats recursively
	 * Keeps track of whether inside a backcompat root or not
	 * @param DOMElement $context: node to start with
	 * @param int $depth: recursion depth
	 * @return array
	 */
	public function parse_recursive(DOMElement $context = null, $depth = 0) {
		$mfs = array();
		$mfElements = $this->getRootMF($context);

		foreach ($mfElements as $node) {
			$is_backcompat = !$this->hasRootMf2($node);

			if ($this->convertClassic && $is_backcompat) {
				$this->backcompat($node);
			}

			$recurse = $this->parse_recursive($node, $depth + 1);

			// set bool flag for nested mf
			$has_nested_mf = (bool) $recurse;

			// parse for root mf
			$result = $this->parseH($node, $is_backcompat, $has_nested_mf);

			// TODO: Determine if clearing this is required?
			$this->elementPrefixParsed($node, 'h');
			$this->elementPrefixParsed($node, 'p');
			$this->elementPrefixParsed($node, 'u');
			$this->elementPrefixParsed($node, 'dt');
			$this->elementPrefixParsed($node, 'e');

			// parseH returned a parsed result
			if ($result) {

				// merge recursive results into current results
				if ($recurse) {
					$result = array_merge_recursive($result, $recurse);
				}

				// currently a nested mf; check if node is an mf property of parent
				if ($depth > 0) {
					$temp_properties = nestedMfPropertyNamesFromElement($node);

					// properties found; set up parsed result in 'properties'
					if (!empty($temp_properties)) {

						foreach ($temp_properties as $property => $prefixes) {
							// Note: handling microformat nesting under multiple conflicting prefixes is not currently specified by the mf2 parsing spec.
							$prefixSpecificResult = $result;
							if (in_array('p-', $prefixes)) {
								$prefixSpecificResult['value'] = (!is_array($prefixSpecificResult['properties']) || empty($prefixSpecificResult['properties']['name'][0])) ? $this->parseP($node) : $prefixSpecificResult['properties']['name'][0];
							} elseif (in_array('e-', $prefixes)) {
								$eParsedResult = $this->parseE($node);
								$prefixSpecificResult['html'] = $eParsedResult['html'];
								$prefixSpecificResult['value'] = $eParsedResult['value'];
							} elseif (in_array('u-', $prefixes)) {
								$prefixSpecificResult['value'] = (!is_array($result['properties']) || empty($result['properties']['url'])) ? $this->parseU($node) : reset($result['properties']['url']);
							} elseif (in_array('dt-', $prefixes)) {
								$parsed_property = $this->parseDT($node);
								$prefixSpecificResult['value'] = ($parsed_property) ? $parsed_property : '';
							}
							$prefixSpecificResult['value'] = is_array($prefixSpecificResult['value']) ? $prefixSpecificResult['value']['value'] : $prefixSpecificResult['value'];

							$mfs['properties'][$property][] = $prefixSpecificResult;
						}

					// otherwise, set up in 'children'
					} else {
						$mfs['children'][] = $result;
					}
				// otherwise, top-level mf
				} else {
					$mfs[] = $result;
				}
			}
		}

		return $mfs;
	}


	/**
	 * Parse From ID
	 *
	 * Given an ID, parse all microformats which are children of the element with
	 * that ID.
	 *
	 * Note that rel values are still document-wide.
	 *
	 * If an element with the ID is not found, an empty skeleton mf2 array structure
	 * will be returned.
	 *
	 * @param string $id
	 * @param bool $htmlSafe = false whether or not to HTML-encode angle brackets in non e-* properties
	 * @return array
	 */
	public function parseFromId($id, $convertClassic=true) {
		$matches = $this->xpath->query("//*[@id='{$id}']");

		if (empty($matches))
			return array('items' => array(), 'rels' => array(), 'alternates' => array());

		return $this->parse($convertClassic, $matches->item(0));
	}

	/**
	 * Get the root microformat elements
	 * @param DOMElement $context
	 * @return DOMNodeList
	 */
	public function getRootMF(DOMElement $context = null) {
		// start with mf2 root class name xpath
		$xpaths = array(
			'(php:function("\\Mf2\\classHasMf2RootClassname", normalize-space(@class)))'
		);

		// add mf1 root class names
		foreach ( $this->classicRootMap as $old => $new ) {
			$xpaths[] = '( contains(concat(" ",normalize-space(@class), " "), " ' . $old . ' ") )';
		}

		// final xpath with OR
		$xpath = '//*[' . implode(' or ', $xpaths) . ']';

		$mfElements = (null === $context)
			? $this->xpath->query($xpath)
			: $this->xpath->query('.' . $xpath, $context);

		return $mfElements;
	}

	/**
	 * Apply the backcompat algorithm to upgrade mf1 classes to mf2.
	 * This method is called recursively.
	 * @param DOMElement $el
	 * @param string $context
	 * @param bool $isParentMf2
	 * @see http://microformats.org/wiki/microformats2-parsing#algorithm
	 */
	public function backcompat(DOMElement $el, $context = '', $isParentMf2 = false) {

		if ( $context ) {
			$mf1Classes = array($context);
		} else {
			$class = str_replace(array("\t", "\n"), ' ', $el->getAttribute('class'));
			$classes = array_filter(explode(' ', $class));
			$mf1Classes = array_intersect($classes, array_keys($this->classicRootMap));
		}

		$elHasMf2 = $this->hasRootMf2($el);

		foreach ($mf1Classes as $classname) {
			// special handling for specific properties
			switch ( $classname )
			{
				case 'hentry':
					$this->upgradeRelTagToCategory($el);

					$rel_bookmark = $this->xpath->query('.//a[contains(concat(" ",normalize-space(@rel)," ")," bookmark ") and @href]', $el);

					if ( $rel_bookmark->length ) {
						foreach ( $rel_bookmark as $tempEl ) {
							$this->addMfClasses($tempEl, 'u-url');
							$this->addUpgraded($tempEl, array('bookmark'));
						}
					}
				break;

				case 'hfeed':
					$this->upgradeRelTagToCategory($el);
				break;

				case 'hproduct':
					$review_and_hreview_aggregate = $this->xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " review ") and contains(concat(" ", normalize-space(@class), " "), " hreview-aggregate ")]', $el);

					if ( $review_and_hreview_aggregate->length ) {
						foreach ( $review_and_hreview_aggregate as $tempEl ) {
							if ( !$this->hasRootMf2($tempEl) ) {
								$this->backcompat($tempEl, 'hreview-aggregate');
								$this->addMfClasses($tempEl, 'p-review h-review-aggregate');
								$this->addUpgraded($tempEl, array('review hreview-aggregate'));
							}
						}
					}

					$review_and_hreview = $this->xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " review ") and contains(concat(" ", normalize-space(@class), " "), " hreview ")]', $el);

					if ( $review_and_hreview->length ) {
						foreach ( $review_and_hreview as $tempEl ) {
							if ( !$this->hasRootMf2($tempEl) ) {
								$this->backcompat($tempEl, 'hreview');
								$this->addMfClasses($tempEl, 'p-review h-review');
								$this->addUpgraded($tempEl, array('review hreview'));
							}
						}
					}

				break;

				case 'hreview-aggregate':
				case 'hreview':
					$item_and_vcard = $this->xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " item ") and contains(concat(" ", normalize-space(@class), " "), " vcard ")]', $el);

					if ( $item_and_vcard->length ) {
						foreach ( $item_and_vcard as $tempEl ) {
							if ( !$this->hasRootMf2($tempEl) ) {
								$this->backcompat($tempEl, 'vcard');
								$this->addMfClasses($tempEl, 'p-item h-card');
								$this->addUpgraded($tempEl, array('item', 'vcard'));
							}
						}
					}

					$item_and_vevent = $this->xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " item ") and contains(concat(" ", normalize-space(@class), " "), " vevent ")]', $el);

					if ( $item_and_vevent->length ) {
						foreach ( $item_and_vevent as $tempEl ) {
							if ( !$this->hasRootMf2($tempEl) ) {
								$this->addMfClasses($tempEl, 'p-item h-event');
								$this->backcompat($tempEl, 'vevent');
								$this->addUpgraded($tempEl, array('item', 'vevent'));
							}
						}
					}

					$item_and_hproduct = $this->xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " item ") and contains(concat(" ", normalize-space(@class), " "), " hproduct ")]', $el);

					if ( $item_and_hproduct->length ) {
						foreach ( $item_and_hproduct as $tempEl ) {
							if ( !$this->hasRootMf2($tempEl) ) {
								$this->addMfClasses($tempEl, 'p-item h-product');
								$this->backcompat($tempEl, 'vevent');
								$this->addUpgraded($tempEl, array('item', 'hproduct'));
							}
						}
					}

					$this->upgradeRelTagToCategory($el);
				break;

				case 'vevent':
					$location_and_vcard = $this->xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " location ") and contains(concat(" ", normalize-space(@class), " "), " vcard ")]', $el);

					if ( $location_and_vcard->length ) {
						foreach ( $location_and_vcard as $tempEl ) {
							if ( !$this->hasRootMf2($tempEl) ) {
								$this->addMfClasses($tempEl, 'p-location h-card');
								$this->backcompat($tempEl, 'vcard');
							}
						}
					}
				break;
			}

			// root class has mf1 properties to be upgraded
			if ( isset($this->classicPropertyMap[$classname]) ) {
				// loop through each property of the mf1 root
				foreach ( $this->classicPropertyMap[$classname] as $property => $data ) {
					$propertyElements = $this->xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " ' . $property . ' ")]', $el);

					// loop through each element with the property
					foreach ( $propertyElements as $propertyEl ) {
						$hasRootMf2 = $this->hasRootMf2($propertyEl);

						// if the element has not been upgraded and we're not inside an mf2 root, recurse
						if ( !$this->isElementUpgraded($propertyEl, $property) && !$isParentMf2 )
						{
							$temp_context = ( isset($data['context']) ) ? $data['context'] : null;
							$this->backcompat($propertyEl, $temp_context, $hasRootMf2);
							$this->addMfClasses($propertyEl, $data['replace']);
						}

						$this->addUpgraded($propertyEl, $property);
					}
				}
			}

			if ( empty($context) && isset($this->classicRootMap[$classname]) && !$elHasMf2 ) {
				$this->addMfClasses($el, $this->classicRootMap[$classname]);
			}
		}

		return;
	}

	/**
	 * Add element + property as upgraded during backcompat
	 * @param DOMElement $el
	 * @param string|array $property
	 */
	public function addUpgraded(DOMElement $el, $property) {
		if ( !is_array($property) ) {
			$property = array($property);
		}

		// add element to list of upgraded elements
		if ( !$this->upgraded->contains($el) ) {
			$this->upgraded->attach($el, $property);
		} else {
			$this->upgraded[$el] = array_merge($this->upgraded[$el], $property);
		}
	}

	/**
	 * Add the provided classes to an element.
	 * Does not add duplicate if class name already exists.
	 * @param DOMElement $el
	 * @param string $classes
	 */
	public function addMfClasses(DOMElement $el, $classes) {
		$existingClasses = str_replace(array("\t", "\n"), ' ', $el->getAttribute('class'));
		$existingClasses = array_filter(explode(' ', $existingClasses));

		$addClasses = array_diff(explode(' ', $classes), $existingClasses);

		if ( $addClasses ) {
			$el->setAttribute('class', $el->getAttribute('class') . ' ' . implode(' ', $addClasses));
		}
	}

	/**
	 * Check an element for mf2 h-* class, typically to determine if backcompat should be used
	 * @param DOMElement $el
	 */
	public function hasRootMf2(\DOMElement $el) {
		$class = str_replace(array("\t", "\n"), ' ', $el->getAttribute('class'));

		// Check for valid mf2 root classnames, not just any classname with a h- prefix.
		return count(mfNamesFromClass($class, 'h-')) > 0;
	}

	/**
	 * Convert Legacy Classnames
	 *
	 * Adds microformats2 classnames into a document containing only legacy
	 * semantic classnames.
	 *
	 * @return Parser $this
	 */
	public function convertLegacy() {
		$doc = $this->doc;
		$xp = new DOMXPath($doc);

		// replace all roots
		foreach ($this->classicRootMap as $old => $new) {
			foreach ($xp->query('//*[contains(concat(" ", normalize-space(@class), " "), " ' . $old . ' ") and not(contains(concat(" ", normalize-space(@class), " "), " ' . $new . ' "))]') as $el) {
				$el->setAttribute('class', $el->getAttribute('class') . ' ' . $new);
			}
		}

		foreach ($this->classicPropertyMap as $oldRoot => $properties) {
			$newRoot = $this->classicRootMap[$oldRoot];
			foreach ($properties as $old => $data) {
				foreach ($xp->query('//*[contains(concat(" ", normalize-space(@class), " "), " ' . $oldRoot . ' ")]//*[contains(concat(" ", normalize-space(@class), " "), " ' . $old . ' ") and not(contains(concat(" ", normalize-space(@class), " "), " ' . $data['replace'] . ' "))]') as $el) {
					$el->setAttribute('class', $el->getAttribute('class') . ' ' . $data['replace']);
				}
			}
		}

		return $this;
	}

	/**
	 * XPath Query
	 *
	 * Runs an XPath query over the current document. Works in exactly the same
	 * way as DOMXPath::query.
	 *
	 * @param string $expression
	 * @param DOMNode $context
	 * @return DOMNodeList
	 */
	public function query($expression, $context = null) {
		return $this->xpath->query($expression, $context);
	}

	/**
	 * Classic Root Classname map
	 * @var array
	 */
	public $classicRootMap = array(
		'vcard' => 'h-card',
		'hfeed' => 'h-feed',
		'hentry' => 'h-entry',
		'hrecipe' => 'h-recipe',
		'hresume' => 'h-resume',
		'vevent' => 'h-event',
		'hreview' => 'h-review',
		'hreview-aggregate' => 'h-review-aggregate',
		'hproduct' => 'h-product',
		'adr' => 'h-adr',
		'geo' => 'h-geo'
	);

	/**
	 * Mapping of mf1 properties to mf2 and the context they're parsed with
	 * @var array
	 */
	public $classicPropertyMap = array(
		'vcard' => array(
			'fn' => array(
				'replace' => 'p-name'
			),
			'honorific-prefix' => array(
				'replace' => 'p-honorific-prefix'
			),
			'given-name' => array(
				'replace' => 'p-given-name'
			),
			'additional-name' => array(
				'replace' => 'p-additional-name'
			),
			'family-name' => array(
				'replace' => 'p-family-name'
			),
			'honorific-suffix' => array(
				'replace' => 'p-honorific-suffix'
			),
			'nickname' => array(
				'replace' => 'p-nickname'
			),
			'email' => array(
				'replace' => 'u-email'
			),
			'logo' => array(
				'replace' => 'u-logo'
			),
			'photo' => array(
				'replace' => 'u-photo'
			),
			'url' => array(
				'replace' => 'u-url'
			),
			'uid' => array(
				'replace' => 'u-uid'
			),
			'category' => array(
				'replace' => 'p-category'
			),
			'adr' => array(
				'replace' => 'p-adr',
			),
			'extended-address' => array(
				'replace' => 'p-extended-address'
			),
			'street-address' => array(
				'replace' => 'p-street-address'
			),
			'locality' => array(
				'replace' => 'p-locality'
			),
			'region' => array(
				'replace' => 'p-region'
			),
			'postal-code' => array(
				'replace' => 'p-postal-code'
			),
			'country-name' => array(
				'replace' => 'p-country-name'
			),
			'label' => array(
				'replace' => 'p-label'
			),
			'geo' => array(
				'replace' => 'p-geo h-geo',
				'context' => 'geo'
			),
			'latitude' => array(
				'replace' => 'p-latitude'
			),
			'longitude' => array(
				'replace' => 'p-longitude'
			),
			'tel' => array(
				'replace' => 'p-tel'
			),
			'note' => array(
				'replace' => 'p-note'
			),
			'bday' => array(
				'replace' => 'dt-bday'
			),
			'key' => array(
				'replace' => 'u-key'
			),
			'org' => array(
				'replace' => 'p-org'
			),
			'organization-name' => array(
				'replace' => 'p-organization-name'
			),
			'organization-unit' => array(
				'replace' => 'p-organization-unit'
			),
			'title' => array(
				'replace' => 'p-job-title'
			),
			'role' => array(
				'replace' => 'p-role'
			),
			'tz' => array(
				'replace' => 'p-tz'
			),
			'rev' => array(
				'replace' => 'dt-rev'
			),
		),
		'hfeed' => array(
			'author' => array(
				'replace' => 'p-author h-card',
				'context' => 'vcard'
			),
			'url' => array(
				'replace' => 'u-url'
			),
			'photo' => array(
				'replace' => 'u-photo'
			),
			'category' => array(
				'replace' => 'p-category'
			),
		),
		'hentry' => array(
			'entry-title' => array(
				'replace' => 'p-name'
			),
			'entry-summary' => array(
				'replace' => 'p-summary'
			),
			'entry-content' => array(
				'replace' => 'e-content'
			),
			'published' => array(
				'replace' => 'dt-published'
			),
			'updated' => array(
				'replace' => 'dt-updated'
			),
			'author' => array(
				'replace' => 'p-author h-card',
				'context' => 'vcard',
			),
			'category' => array(
				'replace' => 'p-category'
			),
		),
		'hrecipe' => array(
			'fn' => array(
				'replace' => 'p-name'
			),
			'ingredient' =>  array(
				'replace' => 'p-ingredient'
				/**
				 * TODO: hRecipe 'value' and 'type' child mf not parsing correctly currently.
				 * Per http://microformats.org/wiki/hRecipe#Property_details, they're experimental.
				 */
			),
			'yield' =>  array(
				'replace' => 'p-yield'
			),
			'instructions' =>  array(
				'replace' => 'e-instructions'
			),
			'duration' =>  array(
				'replace' => 'dt-duration'
			),
			'photo' =>  array(
				'replace' => 'u-photo'
			),
			'summary' =>  array(
				'replace' => 'p-summary'
			),
			'author' =>  array(
				'replace' => 'p-author h-card',
				'context' => 'vcard',
			),
			'nutrition' =>  array(
				'replace' => 'p-nutrition'
			),
			'category' =>  array(
				'replace' => 'p-category'
			),
		),
		'hresume' => array(
			'summary' => array(
				'replace' => 'p-summary'
			),
			'contact' => array(
				'replace' => 'p-contact h-card',
				'context' => 'vcard',
			),
			'education' => array(
				'replace' => 'p-education h-event',
				'context' => 'vevent',
			),
			'experience' => array(
				'replace' => 'p-experience h-event',
				'context' => 'vevent',
			),
			'skill' => array(
				'replace' => 'p-skill'
			),
			'affiliation' => array(
				'replace' => 'p-affiliation h-card',
				'context' => 'vcard',
			),
		),
		'vevent' => array(
			'summary' => array(
				'replace' => 'p-name'
			),
			'dtstart' => array(
				'replace' => 'dt-start'
			),
			'dtend' => array(
				'replace' => 'dt-end'
			),
			'duration' => array(
				'replace' => 'dt-duration'
			),
			'description' => array(
				'replace' => 'p-description'
			),
			'url' => array(
				'replace' => 'u-url'
			),
			'category' => array(
				'replace' => 'p-category'
			),
			'location' => array(
				'replace' => 'p-location',
			),
			'geo' => array(
				'replace' => 'p-location h-geo'
			),
			'attendee' => array(
				'replace' => 'p-attendee h-card',
				'context' => 'vcard'
			)
		),
		'hreview' => array(
			'summary' => array(
				'replace' => 'p-name'
			),
			# fn: see item.fn below
			# photo: see item.photo below
			# url: see item.url below
			'item' => array(
				'replace' => 'p-item h-item',
				'context' => 'item'
			),
			'reviewer' => array(
				'replace' => 'p-author h-card',
				'context' => 'vcard',
			),
			'dtreviewed' => array(
				'replace' => 'dt-published'
			),
			'rating' => array(
				'replace' => 'p-rating'
			),
			'best' => array(
				'replace' => 'p-best'
			),
			'worst' => array(
				'replace' => 'p-worst'
			),
			'description' => array(
				'replace' => 'e-content'
			),
			'category' => array(
				'replace' => 'p-category'
			),
		),
		'hreview-aggregate' => array(
			'summary' => array(
				'replace' => 'p-name'
			),
			# fn: see item.fn below
			# photo: see item.photo below
			# url: see item.url below
			'item' => array(
				'replace' => 'p-item h-item',
				'context' => 'item'
			),
			'rating' => array(
				'replace' => 'p-rating'
			),
			'best' => array(
				'replace' => 'p-best'
			),
			'worst' => array(
				'replace' => 'p-worst'
			),
			'average' => array(
				'replace' => 'p-average'
			),
			'count' => array(
				'replace' => 'p-count'
			),
			'votes' => array(
				'replace' => 'p-votes'
			),
		),
		'hproduct' => array(
			'fn' => array(
				'replace' => 'p-name',
			),
			'photo' => array(
				'replace' => 'u-photo',
			),
			'brand' => array(
				'replace' => 'p-brand',
			),
			'category' => array(
				'replace' => 'p-category',
			),
			'description' => array(
				'replace' => 'p-description',
			),
			'identifier' => array(
				'replace' => 'u-identifier',
			),
			'url' => array(
				'replace' => 'u-url',
			),
			// review is handled in the special processing section to allow for 'review hreview-aggregate'
			'price' => array(
				'replace' => 'p-price'
			),
		),
		'item' => array(
			'fn' => array(
				'replace' => 'p-name'
			),
			'url' => array(
				'replace' => 'u-url'
			),
			'photo' => array(
				'replace' => 'u-photo'
			),
		),
		'adr' => array(
			'post-office-box' => array(
				'replace' => 'p-post-office-box'
			),
			'extended-address' => array(
				'replace' => 'p-extended-address'
			),
			'street-address' => array(
				'replace' => 'p-street-address'
			),
			'locality' => array(
				'replace' => 'p-locality'
			),
			'region' => array(
				'replace' => 'p-region'
			),
			'postal-code' => array(
				'replace' => 'p-postal-code'
			),
			'country-name' => array(
				'replace' => 'p-country-name'
			),
		),
		'geo' => array(
			'latitude' => array(
				'replace' => 'p-latitude'
			),
			'longitude' => array(
				'replace' => 'p-longitude'
			),
		),
	);
}

function parseUriToComponents($uri) {
	$result = array(
		'scheme' => null,
		'authority' => null,
		'path' => null,
		'query' => null,
		'fragment' => null
	);

	$u = @parse_url($uri);

	if(array_key_exists('scheme', $u))
		$result['scheme'] = $u['scheme'];

	if(array_key_exists('host', $u)) {
		if(array_key_exists('user', $u))
			$result['authority'] = $u['user'];
		if(array_key_exists('pass', $u))
			$result['authority'] .= ':' . $u['pass'];
		if(array_key_exists('user', $u) || array_key_exists('pass', $u))
			$result['authority'] .= '@';
		$result['authority'] .= $u['host'];
		if(array_key_exists('port', $u))
			$result['authority'] .= ':' . $u['port'];
	}

	if(array_key_exists('path', $u))
		$result['path'] = $u['path'];

	if(array_key_exists('query', $u))
		$result['query'] = $u['query'];

	if(array_key_exists('fragment', $u))
		$result['fragment'] = $u['fragment'];

	return $result;
}

function resolveUrl($baseURI, $referenceURI) {
	$target = array(
		'scheme' => null,
		'authority' => null,
		'path' => null,
		'query' => null,
		'fragment' => null
	);

	# 5.2.1 Pre-parse the Base URI
	# The base URI (Base) is established according to the procedure of
	# Section 5.1 and parsed into the five main components described in
	# Section 3
	$base = parseUriToComponents($baseURI);

	# If base path is blank (http://example.com) then set it to /
	# (I can't tell if this is actually in the RFC or not, but seems like it makes sense)
	if($base['path'] == null)
		$base['path'] = '/';

	# 5.2.2. Transform References

	# The URI reference is parsed into the five URI components
	# (R.scheme, R.authority, R.path, R.query, R.fragment) = parse(R);
	$reference = parseUriToComponents($referenceURI);

	# A non-strict parser may ignore a scheme in the reference
	# if it is identical to the base URI's scheme.
	# TODO

	if($reference['scheme']) {
		$target['scheme'] = $reference['scheme'];
		$target['authority'] = $reference['authority'];
		$target['path'] = removeDotSegments($reference['path']);
		$target['query'] = $reference['query'];
	} else {
		if($reference['authority']) {
			$target['authority'] = $reference['authority'];
			$target['path'] = removeDotSegments($reference['path']);
			$target['query'] = $reference['query'];
		} else {
			if($reference['path'] == '') {
				$target['path'] = $base['path'];
				if($reference['query']) {
					$target['query'] = $reference['query'];
				} else {
					$target['query'] = $base['query'];
				}
			} else {
				if(substr($reference['path'], 0, 1) == '/') {
					$target['path'] = removeDotSegments($reference['path']);
				} else {
					$target['path'] = mergePaths($base, $reference);
					$target['path'] = removeDotSegments($target['path']);
				}
				$target['query'] = $reference['query'];
			}
			$target['authority'] = $base['authority'];
		}
		$target['scheme'] = $base['scheme'];
	}
	$target['fragment'] = $reference['fragment'];

	# 5.3 Component Recomposition
	$result = '';
	if($target['scheme']) {
		$result .= $target['scheme'] . ':';
	}
	if($target['authority']) {
		$result .= '//' . $target['authority'];
	}
	$result .= $target['path'];
	if($target['query']) {
		$result .= '?' . $target['query'];
	}
	if($target['fragment']) {
		$result .= '#' . $target['fragment'];
	} elseif($referenceURI == '#') {
		$result .= '#';
	}
	return $result;
}

# 5.2.3 Merge Paths
function mergePaths($base, $reference) {
	# If the base URI has a defined authority component and an empty
	# path,
	if($base['authority'] && $base['path'] == null) {
		# then return a string consisting of "/" concatenated with the
		# reference's path; otherwise,
		$merged = '/' . $reference['path'];
	} else {
		if(($pos=strrpos($base['path'], '/')) !== false) {
			# return a string consisting of the reference's path component
			# appended to all but the last segment of the base URI's path (i.e.,
			# excluding any characters after the right-most "/" in the base URI
			# path,
			$merged = substr($base['path'], 0, $pos + 1) . $reference['path'];
		} else {
			# or excluding the entire base URI path if it does not contain
			# any "/" characters).
			$merged = $base['path'];
		}
	}
	return $merged;
}

# 5.2.4.A Remove leading ../ or ./
function removeLeadingDotSlash(&$input) {
	if(substr($input, 0, 3) == '../') {
		$input = substr($input, 3);
	} elseif(substr($input, 0, 2) == './') {
		$input = substr($input, 2);
	}
}

# 5.2.4.B Replace leading /. with /
function removeLeadingSlashDot(&$input) {
	if(substr($input, 0, 3) == '/./') {
		$input = '/' . substr($input, 3);
	} else {
		$input = '/' . substr($input, 2);
	}
}

# 5.2.4.C Given leading /../ remove component from output buffer
function removeOneDirLevel(&$input, &$output) {
	if(substr($input, 0, 4) == '/../') {
		$input = '/' . substr($input, 4);
	} else {
		$input = '/' . substr($input, 3);
	}
	$output = substr($output, 0, strrpos($output, '/'));
}

# 5.2.4.D Remove . and .. if it's the only thing in the input
function removeLoneDotDot(&$input) {
	if($input == '.') {
		$input = substr($input, 1);
	} else {
		$input = substr($input, 2);
	}
}

# 5.2.4.E Move one segment from input to output
function moveOneSegmentFromInput(&$input, &$output) {
	if(substr($input, 0, 1) != '/') {
		$pos = strpos($input, '/');
	} else {
		$pos = strpos($input, '/', 1);
	}

	if($pos === false) {
		$output .= $input;
		$input = '';
	} else {
		$output .= substr($input, 0, $pos);
		$input = substr($input, $pos);
	}
}

# 5.2.4 Remove Dot Segments
function removeDotSegments($path) {
	# 1.  The input buffer is initialized with the now-appended path
	#     components and the output buffer is initialized to the empty
	#     string.
	$input = $path;
	$output = '';

	$step = 0;

	# 2.  While the input buffer is not empty, loop as follows:
	while($input) {
		$step++;

		if(substr($input, 0, 3) == '../' || substr($input, 0, 2) == './') {
			#     A.  If the input buffer begins with a prefix of "../" or "./",
			#         then remove that prefix from the input buffer; otherwise,
			removeLeadingDotSlash($input);
		} elseif(substr($input, 0, 3) == '/./' || $input == '/.') {
			#     B.  if the input buffer begins with a prefix of "/./" or "/.",
			#         where "." is a complete path segment, then replace that
			#         prefix with "/" in the input buffer; otherwise,
			removeLeadingSlashDot($input);
		} elseif(substr($input, 0, 4) == '/../' || $input == '/..') {
			#     C.  if the input buffer begins with a prefix of "/../" or "/..",
			#          where ".." is a complete path segment, then replace that
			#          prefix with "/" in the input buffer and remove the last
			#          segment and its preceding "/" (if any) from the output
			#          buffer; otherwise,
			removeOneDirLevel($input, $output);
		} elseif($input == '.' || $input == '..') {
			#     D.  if the input buffer consists only of "." or "..", then remove
			#         that from the input buffer; otherwise,
			removeLoneDotDot($input);
		} else {
			#     E.  move the first path segment in the input buffer to the end of
			#         the output buffer and any subsequent characters up to, but not including,
			#         the next "/" character or the end of the input buffer
			moveOneSegmentFromInput($input, $output);
		}
	}

	return $output;
}

/** Tools for determining the encoding of documents */
class Encoding {
	/** @var string The default encoding to use when none is detected in the document */
	const DEFAULT_ENCODING = "windows-1252";
	/** @var array The list of known encodings; see https://encoding.spec.whatwg.org/#names-and-labels */
	const ENCODINGS = [
		'unicode-1-1-utf-8' => "UTF-8",
		'unicode11utf8' => "UTF-8",
		'unicode20utf8' => "UTF-8",
		'utf-8' => "UTF-8",
		'utf8' => "UTF-8",
		'x-unicode20utf8' => "UTF-8",
		'866' => "IBM866",
		'cp866' => "IBM866",
		'csibm866' => "IBM866",
		'ibm866' => "IBM866",
		'csisolatin2' => "ISO-8859-2",
		'iso-8859-2' => "ISO-8859-2",
		'iso-ir-101' => "ISO-8859-2",
		'iso8859-2' => "ISO-8859-2",
		'iso88592' => "ISO-8859-2",
		'iso_8859-2' => "ISO-8859-2",
		'iso_8859-2:1987' => "ISO-8859-2",
		'l2' => "ISO-8859-2",
		'latin2' => "ISO-8859-2",
		'csisolatin3' => "ISO-8859-3",
		'iso-8859-3' => "ISO-8859-3",
		'iso-ir-109' => "ISO-8859-3",
		'iso8859-3' => "ISO-8859-3",
		'iso88593' => "ISO-8859-3",
		'iso_8859-3' => "ISO-8859-3",
		'iso_8859-3:1988' => "ISO-8859-3",
		'l3' => "ISO-8859-3",
		'latin3' => "ISO-8859-3",
		'csisolatin4' => "ISO-8859-4",
		'iso-8859-4' => "ISO-8859-4",
		'iso-ir-110' => "ISO-8859-4",
		'iso8859-4' => "ISO-8859-4",
		'iso88594' => "ISO-8859-4",
		'iso_8859-4' => "ISO-8859-4",
		'iso_8859-4:1988' => "ISO-8859-4",
		'l4' => "ISO-8859-4",
		'latin4' => "ISO-8859-4",
		'csisolatincyrillic' => "ISO-8859-5",
		'cyrillic' => "ISO-8859-5",
		'iso-8859-5' => "ISO-8859-5",
		'iso-ir-144' => "ISO-8859-5",
		'iso8859-5' => "ISO-8859-5",
		'iso88595' => "ISO-8859-5",
		'iso_8859-5' => "ISO-8859-5",
		'iso_8859-5:1988' => "ISO-8859-5",
		'arabic' => "ISO-8859-6",
		'asmo-708' => "ISO-8859-6",
		'csiso88596e' => "ISO-8859-6",
		'csiso88596i' => "ISO-8859-6",
		'csisolatinarabic' => "ISO-8859-6",
		'ecma-114' => "ISO-8859-6",
		'iso-8859-6' => "ISO-8859-6",
		'iso-8859-6-e' => "ISO-8859-6",
		'iso-8859-6-i' => "ISO-8859-6",
		'iso-ir-127' => "ISO-8859-6",
		'iso8859-6' => "ISO-8859-6",
		'iso88596' => "ISO-8859-6",
		'iso_8859-6' => "ISO-8859-6",
		'iso_8859-6:1987' => "ISO-8859-6",
		'csisolatingreek' => "ISO-8859-7",
		'ecma-118' => "ISO-8859-7",
		'elot_928' => "ISO-8859-7",
		'greek' => "ISO-8859-7",
		'greek8' => "ISO-8859-7",
		'iso-8859-7' => "ISO-8859-7",
		'iso-ir-126' => "ISO-8859-7",
		'iso8859-7' => "ISO-8859-7",
		'iso88597' => "ISO-8859-7",
		'iso_8859-7' => "ISO-8859-7",
		'iso_8859-7:1987' => "ISO-8859-7",
		'sun_eu_greek' => "ISO-8859-7",
		'csiso88598e' => "ISO-8859-8",
		'csisolatinhebrew' => "ISO-8859-8",
		'hebrew' => "ISO-8859-8",
		'iso-8859-8' => "ISO-8859-8",
		'iso-8859-8-e' => "ISO-8859-8",
		'iso-ir-138' => "ISO-8859-8",
		'iso8859-8' => "ISO-8859-8",
		'iso88598' => "ISO-8859-8",
		'iso_8859-8' => "ISO-8859-8",
		'iso_8859-8:1988' => "ISO-8859-8",
		'visual' => "ISO-8859-8",
		'csiso88598i' => "ISO-8859-8-I",
		'iso-8859-8-i' => "ISO-8859-8-I",
		'logical' => "ISO-8859-8-I",
		'csisolatin6' => "ISO-8859-10",
		'iso-8859-10' => "ISO-8859-10",
		'iso-ir-157' => "ISO-8859-10",
		'iso8859-10' => "ISO-8859-10",
		'iso885910' => "ISO-8859-10",
		'l6' => "ISO-8859-10",
		'latin6' => "ISO-8859-10",
		'iso-8859-13' => "ISO-8859-13",
		'iso8859-13' => "ISO-8859-13",
		'iso885913' => "ISO-8859-13",
		'iso-8859-14' => "ISO-8859-14",
		'iso8859-14' => "ISO-8859-14",
		'iso885914' => "ISO-8859-14",
		'csisolatin9' => "ISO-8859-15",
		'iso-8859-15' => "ISO-8859-15",
		'iso8859-15' => "ISO-8859-15",
		'iso885915' => "ISO-8859-15",
		'iso_8859-15' => "ISO-8859-15",
		'l9' => "ISO-8859-15",
		'iso-8859-16' => "ISO-8859-16",
		'cskoi8r' => "KOI8-R",
		'koi' => "KOI8-R",
		'koi8' => "KOI8-R",
		'koi8-r' => "KOI8-R",
		'koi8_r' => "KOI8-R",
		'koi8-ru' => "KOI8-U",
		'koi8-u' => "KOI8-U",
		'csmacintosh' => "macintosh",
		'mac' => "macintosh",
		'macintosh' => "macintosh",
		'x-mac-roman' => "macintosh",
		'dos-874' => "windows-874",
		'iso-8859-11' => "windows-874",
		'iso8859-11' => "windows-874",
		'iso885911' => "windows-874",
		'tis-620' => "windows-874",
		'windows-874' => "windows-874",
		'cp1250' => "windows-1250",
		'windows-1250' => "windows-1250",
		'x-cp1250' => "windows-1250",
		'cp1251' => "windows-1251",
		'windows-1251' => "windows-1251",
		'x-cp1251' => "windows-1251",
		'ansi_x3.4-1968' => "windows-1252",
		'ascii' => "windows-1252",
		'cp1252' => "windows-1252",
		'cp819' => "windows-1252",
		'csisolatin1' => "windows-1252",
		'ibm819' => "windows-1252",
		'iso-8859-1' => "windows-1252",
		'iso-ir-100' => "windows-1252",
		'iso8859-1' => "windows-1252",
		'iso88591' => "windows-1252",
		'iso_8859-1' => "windows-1252",
		'iso_8859-1:1987' => "windows-1252",
		'l1' => "windows-1252",
		'latin1' => "windows-1252",
		'us-ascii' => "windows-1252",
		'windows-1252' => "windows-1252",
		'x-cp1252' => "windows-1252",
		'cp1253' => "windows-1253",
		'windows-1253' => "windows-1253",
		'x-cp1253' => "windows-1253",
		'cp1254' => "windows-1254",
		'csisolatin5' => "windows-1254",
		'iso-8859-9' => "windows-1254",
		'iso-ir-148' => "windows-1254",
		'iso8859-9' => "windows-1254",
		'iso88599' => "windows-1254",
		'iso_8859-9' => "windows-1254",
		'iso_8859-9:1989' => "windows-1254",
		'l5' => "windows-1254",
		'latin5' => "windows-1254",
		'windows-1254' => "windows-1254",
		'x-cp1254' => "windows-1254",
		'cp1255' => "windows-1255",
		'windows-1255' => "windows-1255",
		'x-cp1255' => "windows-1255",
		'cp1256' => "windows-1256",
		'windows-1256' => "windows-1256",
		'x-cp1256' => "windows-1256",
		'cp1257' => "windows-1257",
		'windows-1257' => "windows-1257",
		'x-cp1257' => "windows-1257",
		'cp1258' => "windows-1258",
		'windows-1258' => "windows-1258",
		'x-cp1258' => "windows-1258",
		'x-mac-cyrillic' => "x-mac-cyrillic",
		'x-mac-ukrainian' => "x-mac-cyrillic",
		'chinese' => "GBK",
		'csgb2312' => "GBK",
		'csiso58gb231280' => "GBK",
		'gb2312' => "GBK",
		'gb_2312' => "GBK",
		'gb_2312-80' => "GBK",
		'gbk' => "GBK",
		'iso-ir-58' => "GBK",
		'x-gbk' => "GBK",
		'gb18030' => "gb18030",
		'big5' => "Big5",
		'big5-hkscs' => "Big5",
		'cn-big5' => "Big5",
		'csbig5' => "Big5",
		'x-x-big5' => "Big5",
		'cseucpkdfmtjapanese' => "EUC-JP",
		'euc-jp' => "EUC-JP",
		'x-euc-jp' => "EUC-JP",
		'csiso2022jp' => "ISO-2022-JP",
		'iso-2022-jp' => "ISO-2022-JP",
		'csshiftjis' => "Shift_JIS",
		'ms932' => "Shift_JIS",
		'ms_kanji' => "Shift_JIS",
		'shift-jis' => "Shift_JIS",
		'shift_jis' => "Shift_JIS",
		'sjis' => "Shift_JIS",
		'windows-31j' => "Shift_JIS",
		'x-sjis' => "Shift_JIS",
		'cseuckr' => "EUC-KR",
		'csksc56011987' => "EUC-KR",
		'euc-kr' => "EUC-KR",
		'iso-ir-149' => "EUC-KR",
		'korean' => "EUC-KR",
		'ks_c_5601-1987' => "EUC-KR",
		'ks_c_5601-1989' => "EUC-KR",
		'ksc5601' => "EUC-KR",
		'ksc_5601' => "EUC-KR",
		'windows-949' => "EUC-KR",
		'csiso2022kr' => "replacement",
		'hz-gb-2312' => "replacement",
		'iso-2022-cn' => "replacement",
		'iso-2022-cn-ext' => "replacement",
		'iso-2022-kr' => "replacement",
		'replacement' => "replacement",
		'unicodefffe' => "UTF-16BE",
		'utf-16be' => "UTF-16BE",
		'csunicode' => "UTF-16LE",
		'iso-10646-ucs-2' => "UTF-16LE",
		'ucs-2' => "UTF-16LE",
		'unicode' => "UTF-16LE",
		'unicodefeff' => "UTF-16LE",
		'utf-16' => "UTF-16LE",
		'utf-16le' => "UTF-16LE",
		'x-user-defined' => "x-user-defined",
	];
	/** @var string The PCRE pattern for a Content-Type HTTP header-field, chopping it up into three main sections */
	const TYPE_PATTERN = <<<'PATTERN'
	/^
		[\t\r\n ]*                              # optional leading whitespace
		([^\/]+)                                # type  
		\/                                      # type-subtype delimiter
		([^;]+)                                 # subtype (possibly with trailing whitespace)
		(;.*)?                                  # optional parameters, to be parsed separately
		[\t\r\n ]*                              # optional trailing whitespace
	$/sx
PATTERN;
	/** @var string  The PCRE pattern for an HTTP Content-Type header-field's parameters, splitting them into a series of names and values*/
	const PARAM_PATTERN = <<<'PATTERN'
	/
		[;\t\r\n ]*                             # parameter delimiter and leading whitespace, all optional
		([^=;]*)                                # parameter name; may be empty
		(?:=                                    # parameter name-value delimiter
			(
				"(?:\\"|[^"])*(?:"|$)[^;]*      # quoted parameter value and optional garbage
				|[^;]*                          # unquoted parameter value (possibly with trailing whitespace)
			)
		)?
		;?                                      # optional trailing parameter delimiter
		[\t\r\n ]*                              # optional trailing whitespace
	/sx
PATTERN;
	/** @var string The PCRE pattern for an HTTP token, used to validate the nameof a parameter */
	const TOKEN_PATTERN = '/^[A-Za-z0-9!#$%&\'*+\-\.\^_`|~]+$/s';
	/** @var string The PCRE pattern for a "bare" (unquoted) parameter value, used for validation */
	const BARE_VALUE_PATTERN = '/^[\t\x{20}-\x{7E}\x{80}-\x{FF}]+$/s';
	/** @var string The PCRE pattern for a quoted parameter value, used for validation */
	const QUOTED_VALUE_PATTERN = '/^"((?:\\\"|[\t !\x{23}-\x{7E}\x{80}-\x{FF}])*)(?:"|$)/s';
	/** @var string The PCRE pattern for an escaped character in a quoted parameter value, used to unescape via replacement */
	const ESCAPE_PATTERN = '/\\\(.)/s';
	/** @var string The PCRE pattern for all valid UTF-8 characters */
	const UTF8_PATTERN = <<<'PATTERN'
	/^(?:
		# Single-byte
		[\x{00}-\x{7F}]+
		# Two-byte
		|[\x{C2}-\x{DF}][\x{80}-\x{BF}]
		# Three-byte excluding surrogates
		|\x{E0}[\x{A0}-\x{BF}][\x{80}-\x{BF}]
		|\x{ED}[\x{80}-\x{9F}][\x{80}-\x{BF}]
		|[\x{E1}-\x{EC}\x{EE}\x{EF}][\x{80}-\x{BF}]{2}
		# Four-byte
		|\x{F0}[\x{90}-\x{BF}][\x{80}-\x{BF}]{2}
		|\x{F4}[\x{80}-\x{8F}][\x{80}-\x{BF}]{2}
		|[\x{F1}-\x{F3}][\x{80}-\x{BF}]{3}
	)*$/sx
PATTERN;
	/** @var array A list of standard encoding labels which DOMDocument either does not know or does not map to the correct encoding; this is a worst-case list taken from PHP 5.6 on Windows with some exclusions for encodings which are completely unsupported */
	const ENCODING_NAUGHTY_LIST = [
		"unicode-1-1-utf-8", "unicode11utf8", "unicode20utf8", "x-unicode20utf8",
		"iso88592", "iso88593", "iso88594", "iso88595", "csiso88596e",
		"csiso88596i", "iso-8859-6-e", "iso-8859-6-i", "iso88596", "iso88597",
		"sun_eu_greek", "csiso88598e", "iso-8859-8-e", "iso88598", "visual",
		"csiso88598i", "iso-8859-8-i", "logical", "iso885910", "iso885913",
		"iso885914", "csisolatin9", "iso885915", "l9", "koi", "koi8", "koi8_r",
		"x-mac-roman", "dos-874", "iso-8859-11", "iso8859-11", "iso885911",
		"tis-620", "x-cp1250", "x-cp1251", "ansi_x3.4-1968", "ascii", "cp819",
		"csisolatin1", "ibm819", "iso-8859-1", "iso-ir-100", "iso8859-1",
		"iso88591", "iso_8859-1", "iso_8859-1:1987", "l1", "latin1",
		"us-ascii", "x-cp1252", "x-cp1253", "iso88599", "x-cp1254",
		"x-cp1255", "x-cp1256", "x-cp1257", "cp1258", "windows-1258",
		"x-mac-ukrainian", "chinese", "csgb2312", "csiso58gb231280", "gb2312",
		"gb_2312", "gb_2312-80", "gbk", "iso-ir-58", "big5", "cn-big5",
		"csbig5", "x-x-big5", "x-euc-jp", "ms932", "windows-31j", "x-sjis",
		"cseuckr", "euc-kr", "x-user-defined", "replacement",
	];
	/** @var array A List of canonical encoding names DOMDocument does not understand, with liases to labels it does understand */
	const ENCODING_ALIAS_MAP = [
		'windows-1258' => "x-cp1258",
		'GBK' => "x-gbk",
		'Big5' => "big5-hkscs",
		'EUC-KR' => "korean",
		'x-user-defined' => "windows-1256", // this is technically not correct, but x-user-defined is not likely to be used in HTML documents; it is used to represent binary data in JavaScript; windows-1256 is used as a substitute as every byte is assigned to a character, so text can be converted back into bytes with no loss
	];
	/** @var string A UTF-8 byte order mark */
	const BOM_UTF8 = "\xEF\xBB\xBF";
	/** @var string A UTF-16 (big-endian) byte order mark */
	const BOM_UTF16BE = "\xFE\xFF";
	/** @var string A UTF-16 (little-endian) byte order mark */
	const BOM_UTF16LE = "\xFF\xFE";
	/** @var string A PCRE pattern which finds the insertion point inside the HTML <head> element of a document; the pattern takes some shortcuts, but nothing which is likely to affect documents which actually have microformat metadata */
	const HTML_HEADER_PATTERN = <<<PATTERN
	/^(?:\x{EF}\x{BB}\x{BF})?							# Optional UTF-8 BOM at start of string
	(?:													# Followed by...
		\s+												# Whitespace or
		|<!DOCTYPE[^>]*>								# DOCTYPE or
		|<!--(?:-?>|(?:[^-]|-(?!->))*-->)				# Comment or
		|<\?[^>]*>										# Processing instruction or XML declaration
	)*													# ... zero or more times
	(?:<html											# Followed by a possible <html> start tag
		(?:\s+											# ... with possible attributes
			(?:\s*[^\s=>]*
				(?:=
					(?:
						"[^"]*"
						|'[^']*'
						|[^ >]*
					)?
				)?
			)*
		)?
		\/?\s*>
	)?
	(?:													# Followed by...
		\s+												# Whitespace or
		|<!DOCTYPE[^>]*>								# DOCTYPE or
		|<!--(?:-?>|(?:[^-]|-(?!->))*-->)				# Comment or
		|<\?[^>]*>										# Processing instruction or XML declaration
	)*													# ... zero or more times
	(?:<head											# Followed by a possible <head> start tag
		(?:\s+											# ... with possible attributes
			(?:\s*[^\s=>]*
				(?:=
					(?:
						"[^"]*"
						|'[^']*'
						|[^ >]*
					)?
				)?
			)*
		)?
		\/?\s*>
	)?
	/six
PATTERN;

	/** Matches an encoding label to a known encoding name in the WHATWG encoding list
	 * 
	 * If the label does not match any known encoding, and empty string is returned.
	 * 
	 * @param string $label The label to match e.g. "utf8", " FOO_bar "
	 * @param bool $excludeNaughty Whether or not to exclude labels DOMDocument does not understand
	 * @return string The canonical name of the encoding if matched (e.g. "UTF-8"), or an empty string in case of failure 
	 */
	public static function matchEncodingLabel($label, $excludeNaughty = false) {
		$label = strtolower(trim($label, " \t\r\n\x0C")); // see https://infra.spec.whatwg.org/#ascii-whitespace
		if ($excludeNaughty && in_array($label, self::ENCODING_NAUGHTY_LIST)) {
			return "";
		}
		if (array_key_exists($label, self::ENCODINGS)) {
			return self::ENCODINGS[$label];
		}
		return "";
	}

	/** Parses a Content-Type header-field to extract the type and charset, if any
	 * 
	 * @param string $type
	 * @return array An array containing keys for "type" and "charset"
	 */
	public static function parseContentType($type) {
		$out = [
			'type' => "",
			'charset' => "",
		];
		if (preg_match(self::TYPE_PATTERN, $type, $match)) {
			list($mimeType, $type, $subtype, $params) = array_pad($match, 4, "");
			$type = preg_match(self::TOKEN_PATTERN, $type) ? $type : "";
			$subtype = rtrim($subtype, "\t\r\n ");
			$subtype = preg_match(self::TOKEN_PATTERN, $subtype) ? $subtype : ""; 
			if (strlen($type) && strlen($subtype)) {
				$out['type'] = strtolower($type) . "/" . strtolower($subtype);
				// parse parameters looking for a charset one
				if (preg_match_all(self::PARAM_PATTERN, $params, $matches, \PREG_SET_ORDER)) {
					foreach ($matches as $match) {
						list($param, $name, $value) = array_pad($match, 3, "");
						$name = strtolower(preg_match(self::TOKEN_PATTERN, $name) ? $name : "");
						if ($name === "charset" && strlen($value)) {
							if ($value[0] === '"') {
								if (preg_match(self::QUOTED_VALUE_PATTERN, $value, $match)) {
									$out['charset'] = preg_replace(self::ESCAPE_PATTERN, '$1', $match[1]);
									return $out;
								}
							} else {
								$value = rtrim($value, "\t\r\n ");
								if (preg_match(self::BARE_VALUE_PATTERN, $value)) {
									$out['charset'] = $value;
									return $out;
								}
							}
						}
					}
				}
			}
		}
		return $out;
	}
}
