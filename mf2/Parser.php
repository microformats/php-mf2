<?php

namespace mf2;

use DOMDocument,
    DOMElement,
    DOMXPath,
    DOMNode,
    DOMNodeList,
    DateTime,
    Exception;

class Parser {
    /**
     * 	The baseurl (if any) to use for this parse
     */
    public $baseurl;

    /**
     * A DOMXPath object which can be used to query over any fragment
     */
    protected $xpath;
    
    /**
     * @var bool Whether or not to output datetimes as strings
     */
    public $stringDateTimes = false;

    /**
     * Constructor
     * @param mixed $input The data to parse. Can be a string URL (TODO), a string of DOM or a DOMDocument
     */
    public function __construct($input, $baseurl = null, $convertClassic = false) {
        // TODO: Check is URL
        // For the moment: assume string = string of HTML
        if (is_string($input)) {
            if (strtolower(mb_detect_encoding($input)) == 'utf-8') {
                $input = mb_convert_encoding($input, 'HTML-ENTITIES', "UTF-8");
            }

            // Perform classic microformats conversion if required
            if ($convertClassic != false)
                $input = $this->convertClassic($input);

            $doc = new DOMDocument(null, 'UTF-8');
            @$doc->loadHTML($input); // TODO: handle seriously malformed HTML better
        }
        elseif (is_a($input, 'DOMDocument')) {
            $doc = $input;
        }

        $this->xpath = new DOMXPath($doc);

        // TODO: Check for <base> if $baseURL not supplied
        $this->baseurl = $baseurl;
    }

    // !Utility Functions
    
    /**
     * Collapse Whitespace
     * 
     * Collapses any sequences of whitespace within a string into a single space
     * character.
     * 
     * @param string $str
     * @return string
     */
    public static function collapseWhitespace($str) {
        return preg_replace('/\s+/', ' ', $str);
    }

    /**
     * Microformat Name From Class string
     * 
     * Given the value of @class, get the relevant mf classname (e.g. h-card, 
     * p-name). Matches the first if there are multiple.
     * 
     * For h-*, it returns an array containing all full h-* names, but for 
     * property names it returns a string and strips the prefix.
     * 
     * @param string $class A space delimited list of classnames
     * @param string $prefix The prefix to look for
     * @return string|array The prefixed name of the first microfomats class found or false
     */
    public static function mfNameFromClass($class, $prefix = 'h-') {
        $classes = explode(' ', $class);
        $matches = array();
        
        foreach ($classes as $classname) {
            if (stristr(' ' . $classname, ' ' . $prefix) !== false) {
                $matches[] = ($prefix === 'h-') ? $classname : substr($classname, strlen($prefix));
            }
        }

        return ($prefix == 'h-') ? $matches : $matches[0];
    }
    
    /**
     * Get Nested µf Property Name From Class
     * 
     * Returns the first p-, u-, dt- or e- prefixed classname it finds in a 
     * space-separated string.
     * 
     * @param string $class
     * @return string|null
     */
    public static function nestedMfPropertyNameFromClass($class) {
        $prefixes = array('p-', 'u-', 'dt-', 'e-');
        
        foreach (explode(' ', $class) as $classname) {
            foreach ($prefixes as $prefix) {
                if (stristr($classname, $prefix))
                    return self::mfNameFromClass($classname, $prefix);
            }
        }
        
        return null;
    }

    /**
     * Wraps mf_name_from_class to handle an element as input (common)
     * 
     * @param DOMElement $e The element to get the classname for
     * @param string $prefix The prefix to look for
     * @return mixed See return value of mf2\Parser::mfNameFromClass()
     */
    static function mfNameFromElement(\DOMElement $e, $prefix = 'h-') {
        $class = $e->getAttribute('class');
        return Parser::mfNameFromClass($class, $prefix);
    }
    
    /**
     * Wraps nestedMfPropertyNameFromClass to handle an element as input
     */
    static function nestedMfPropertyNameFromElement(\DOMElement $e) {
        $class = $e->getAttribute('class');
        return self::nestedMfPropertyNameFromClass($class);
    }
    
    /**
     * Checks to see if a DOMElement has already been parsed
     * 
     * @param DOMElement $e The element to check
     * @param string $type	The type of parsing to check for
     * @return bool Whether or not $e has already been parsed as $type
     */
    static function mfElementParsed(\DOMElement $e, $type) {
        return ($e->getAttribute('data-' . $type . '-parsed') == 'true');
    }

    // !Parsing Functions
    /**
     * Given an element with class="p-*", get it’s value
     * 
     * @param DOMElement $p The element to parse
     * @return string The plaintext value of $p, dependant on type
     * @todo Make this adhere to value-class
     */
    public function parseP(\DOMElement $p) {
        if ($p->tagName == 'img' and $p->getAttribute('alt') !== '') {
            $pValue = $p->getAttribute('alt');
        } elseif ($p->tagName == 'abbr' and $p->getAttribute('title') !== '') {
            $pValue = $p->getAttribute('title');
        } elseif ($p->tagName == 'data' and $p->getAttribute('value') !== '') {
            $pValue = $p->getAttribute('value');
        } else {
            // Use innertext
            $pValue = trim($p->nodeValue);
        }
        
        return self::collapseWhitespace($pValue);
    }

    /**
     * Given an element with class="u-*", get the value of the URL
     * 
     * @param DOMElement $u The element to parse
     * @return string The plaintext value of $u, dependant on type
     * @todo make this adhere to value-class
     */
    public function parseU(\DOMElement $u) {
        if (($u->tagName == 'a' or $u->tagName == 'area') and $u->getAttribute('href') !== null) {
            $uValue = $u->getAttribute('href');
        } elseif ($u->tagName == 'img' and $u->getAttribute('src') !== null) {
            $uValue = $u->getAttribute('src');
        } elseif ($u->tagName == 'object' and $u->getAttribute('data') !== null) {
            $uValue = $u->getAttribute('data');
        } elseif ($u->tagName == 'abbr' and $u->getAttribute('title') !== null) {
            $uValue = $u->getAttribute('title');
        } elseif ($u->tagName == 'data' and $u->getAttribute('value') !== null) {
            $uValue = $u->getAttribute('value');
        } else {
            // TODO: Check for element contents == a valid URL
            $uValue = false;
        }

        if ($uValue !== false) {
            $host = parse_url($uValue, PHP_URL_HOST);
            $scheme = parse_url($uValue, PHP_URL_SCHEME);
            if (empty($host) and empty($host) and !empty($this->baseurl)) {
                $uValue = $this->baseurl . $uValue;
            }
        }

        return $uValue;
    }

    /**
     * Given an element with class="dt-*", get the value of the datetime as a php date object
     * 
     * @param DOMElement $dt The element to parse
     * @return DateTime An object representing $dt
     */
    public function parseDT(\DOMElement $dt) {
        // Check for value-class pattern
        $valueClassChildren = $this->xpath->query('.//*[contains(concat(" ", @class, " "), " value ")]', $dt);
        $dtValue = false;

        if ($valueClassChildren->length > 0) {
            // They’re using value-class (awkward bugger :)
            $dateParts = array();

            foreach ($valueClassChildren as $e) {
                if ($e->tagName == 'img' or $e->tagName == 'area') {
                    // Use @alt
                    $alt = $e->getAttribute('alt');
                    if (!empty($alt))
                        $dateParts[] = $alt;
                }
                elseif ($e->tagName == 'data') {
                    // Use @value, otherwise innertext
                    $value = $e->hasAttribute('value') ? $e->getAttribute('value') : trim($e->nodeValue);
                    if (!empty($value))
                        $dateParts[] = $value;
                }
                elseif ($e->tagName == 'abbr') {
                    // Use @title, otherwise innertext
                    $title = $e->hasAttribute('title') ? $e->getAttribute('title') : trim($e->nodeValue);
                    if (!empty($title))
                        $dateParts[] = $title;
                }
                elseif ($e->tagName == 'del' or $e->tagName == 'ins' or $e->tagName == 'time') {
                    // Use @datetime if available, otherwise innertext
                    $dtAttr = ($e->hasAttribute('datetime')) ? $e->getAttribute('datetime') : trim($e->nodeValue);
                    if (!empty($dtAttr))
                        $dateParts[] = $dtAttr;
                }
                else {
                    // Use innertext
                    if (!empty($e->nodeValue))
                        $dateParts[] = trim($e->nodeValue);
                }
            }

            // Look through dateParts
            $datePart = '';
            $timePart = '';
            foreach ($dateParts as $part) {
                // Is this part a full ISO8601 datetime?
                if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(Z?[+|-]\d{2}:?\d{2})?$/', $part)) {
                    // Break completely, we’ve got our value
                    $dtValue = date_create($part);
                    break;
                } else {
                    // Is the current part a valid time(+TZ?) AND no other time reprentation has been found?
                    if ((preg_match('/\d{2}:\d{2}(Z?[+|-]\d{2}:?\d{2})?/', $part) or preg_match('/\d{1,2}[a|p]m/', $part)) and empty($timePart)) {
                        $timePart = $part;
                    } elseif (preg_match('/\d{4}-\d{2}-\d{2}/', $part) and empty($datePart)) {
                        // Is the current part a valid date AND no other date reprentation has been found?
                        $datePart = $part;
                    }
                }
            }

            // If we have both a $datePart and $timePart, create date from those
            if (!empty($datePart) and !empty($timePart)) {
                $dateTime = $datePart . ' ' . $timePart;
                $dtValue = date_create($dateTime);
            }
            // Otherwise, fail (TODO: perhaps try to use any parts we do have to build an approximate date?)
        } else {
            // Not using value-class (phew).
            if ($dt->tagName == 'img' or $dt->tagName == 'area') {
                // Use @alt
                // Is it an entire dt?
                $alt = $dt->getAttribute('alt');
                if (!empty($alt)) {
                    $dtValue = date_create($alt);
                }
            } elseif ($dt->tagName == 'data') {
                // Use @value, otherwise innertext
                // Is it an entire dt?
                $value = $dt->getAttribute('value');
                if (!empty($value)) {
                    $dtValue = date_create($value);
                } else {
                    $dtValue = date_create($dt->nodeValue);
                }
            } elseif ($dt->tagName == 'abbr') {
                // Use @title, otherwise innertext
                // Is it an entire dt?
                $title = $dt->getAttribute('title');
                if (!empty($title)) {
                    $dtValue = date_create($title);
                } else {
                    $dtValue = date_create($dt->nodeValue);
                }
            } elseif ($dt->tagName == 'del' or $dt->tagName == 'ins' or $dt->tagName == 'time') {
                // Use @datetime if available, otherwise innertext
                // Is it an entire dt?
                $dtAttr = $dt->getAttribute('datetime');
                if (!empty($dtAttr)) {
                    $dtValue = date_create($dtAttr);
                } else {
                    $dtValue = date_create($dt->nodeValue);
                }
            } else {
                // Use innertext
                $dtValue = date_create($dt->nodeValue);
            }
        }

        // Whatever happened, $dtValue is now either a \DateTime or false.
        if ($dtValue instanceof DateTime and $this->stringDateTimes)
            return $dtValue->format(DateTime::W3C);
        
        return $dtValue;
    }

    /**
     * 	Given the root element of some embedded markup, return a string representing that markup
     *
     * 	@param DOMElement $e The element to parse
     * 	@return string $e’s innerHTML
     */
    public function parseE(\DOMElement $e) {
        $return = '';
        foreach ($e->childNodes as $node) {
            $return .= $node->C14N();
        }
        return $return;
    }

    /**
     * Recursively parse microformats
     * 
     * @param DOMElement $e The element to parse
     * @return array A representation of the values contained within microformat $e
     */
    public function parseH(\DOMElement $e) {
        // If it’s already been parsed (e.g. is a child mf), skip
        if (Parser::mfElementParsed($e, 'h'))
            return false;

        // Get current µf name
        $mfTypes = Parser::mfNameFromElement($e);

        // Initalise var to store the representation in
        $return = array();
        $children = array();

        // Handle nested microformats (h-*)
        foreach ($this->xpath->query('.//*[contains(concat(" ", @class)," h-")]', $e) as $subMF) {
            // Parse
            $result = $this->parseH($subMF);
            
            // TODO: Populate $result->value

            // Does this µf have a property name other than h-*?
            $property = self::nestedMfPropertyNameFromElement($subMF);
            if (!empty($property)) {
                // Yes: Put it under that array
                $return[$property][] = $result;
                // Set $subMF as parsed for the other property
                // TODO: Clean this
                $subMF->setAttribute('data-p-parsed', 'true');
                $subMF->setAttribute('data-u-parsed', 'true');
                $subMF->setAttribute('data-e-parsed', 'true');
                $subMF->setAttribute('data-dt-parsed', 'true');
            } else {
                // No: It’s a plain child, put it in [children]
                $children[] = $result;
            }

            // Make sure this sub-mf won’t get parsed as a top level mf
            $subMF->setAttribute('data-h-parsed', 'true');
        }

        // Handle p-*
        foreach ($this->xpath->query('.//*[contains(concat(" ", @class) ," p-")]', $e) as $p) {
            if (Parser::mfElementParsed($p, 'p'))
                continue;

            $pValue = $this->parseP($p);

            // Add the value to the array for this property type
            $return[Parser::mfNameFromElement($p, 'p-')][] = $pValue;

            // Make sure this sub-mf won’t get parsed as a top level mf
            $p->setAttribute('data-p-parsed', 'true');
        }

        // Handle u-*
        foreach ($this->xpath->query('.//*[contains(@class,"u-")]', $e) as $u) {
            if (Parser::mfElementParsed($u, 'u'))
                continue;

            $uValue = $this->parseU($u);

            // Add the value to the array for this property type
            $return[Parser::mfNameFromElement($u, 'u-')][] = $uValue;

            // Make sure this sub-mf won’t get parsed as a top level mf
            $u->setAttribute('data-u-parsed', 'true');
        }

        // Handle dt-*
        foreach ($this->xpath->query('.//*[contains(concat(" ", @class), " dt-")]', $e) as $dt) {
            if (Parser::mfElementParsed($dt, 'dt'))
                continue;

            $dtValue = $this->parseDT($dt);

            if ($dtValue) {
                // Add the value to the array for this property type
                $return[Parser::mfNameFromElement($dt, 'dt-')][] = $dtValue;
            }
            // Make sure this sub-mf won’t get parsed as a top level mf
            $dt->setAttribute('data-dt-parsed', 'true');
        }

        // TODO: Handle e-* (em)
        foreach ($this->xpath->query('.//*[contains(concat(" ", @class)," e-")]', $e) as $em) {
            if (Parser::mfElementParsed($em, 'e'))
                continue;

            $eValue = $this->parseE($em);

            if ($eValue) {
                // Add the value to the array for this property type
                $return[Parser::mfNameFromElement($em, 'e-')][] = $eValue;
            }
            // Make sure this sub-mf won’t get parsed as a top level mf
            $em->setAttribute('data-e-parsed', 'true');
        }

        // !Implied Properties
        // Check for p-name
        if (!array_key_exists('name', $return)) {
            try {
                // Look for img @alt
                if ($e->tagName == 'img' and $e->getAttribute('alt') != '')
                    throw new Exception($e->getAttribute('alt'));

                // Look for nested img @alt
                foreach ($this->xpath->query('./img[count(preceding-sibling::*)+count(following-sibling::*)=0]', $e) as $em) {
                    if ($em->getAttribute('alt') != '')
                        throw new Exception($em->getAttribute('alt'));
                }

                // Look for double nested img @alt
                foreach ($this->xpath->query('./*[count(preceding-sibling::*)+count(following-sibling::*)=0]/img[count(preceding-sibling::*)+count(following-sibling::*)=0]', $e) as $em) {
                    if ($em->getAttribute('alt') != '')
                        throw new Exception($em->getAttribute('alt'));
                }

                throw new Exception(trim($e->nodeValue));
            } catch (Exception $exc) {
                $return['name'][] = $exc->getMessage();
            }
        }

        // Check for u-photo
        if (!array_key_exists('photo', $return)) {
            // Look for img @src
            try {
                if ($e->tagName == 'img')
                    throw new Exception($e->getAttribute('src'));

                // Look for nested img @src
                foreach ($this->xpath->query('./img[count(preceding-sibling::img)+count(following-sibling::img)=0]', $e) as $em) {
                    if ($em->getAttribute('src') != '')
                        throw new Exception($em->getAttribute('src'));
                }

                // Look for double nested img @src
                foreach ($this->xpath->query('./*[count(preceding-sibling::img)+count(following-sibling::img)=0]/img[count(preceding-sibling::img)+count(following-sibling::img)=0]', $e) as $em) {
                    if ($em->getAttribute('src') != '')
                        throw new Exception($em->getAttribute('src'));
                }
            } catch (Exception $exc) {
                $return['photo'][] = $exc->getMessage();
            }
        }

        // Check for u-url
        if (!array_key_exists('url', $return)) {
            // Look for img @src
            // @todo resolve relative URLs
            if ($e->tagName == 'a')
                $return['url'][] = $e->getAttribute('href');

            // Look for nested img @src
            foreach ($this->xpath->query('./a[count(preceding-sibling::a)+count(following-sibling::a)=0]', $e) as $em) {
                $return['url'][] = $em->getAttribute('href');
                break;
            }
        }

        // Phew. Return the final result.
        $parsed = array(
            'type' => $mfTypes,
            'properties' => $return
        );
        if (!empty($children))
            $parsed['children'] = array_values(array_filter($children));
        return $parsed;
    }

    /**
     * Kicks off the parsing routine
     * 
     * @return array An array containing all the µfs found in the current document
     */
    public function parse() {
        $mfs = array();

        foreach ($this->xpath->query('//*[contains(concat(" ",  @class), " h-")]') as $node) {
            // For each microformat
            $result = $this->parseH($node);

            // Add the value to the array for this property type
            $mfs[] = $result;
        }

        return array('items' => array_values(array_filter($mfs)));
    }

    /**
     * Convert Classic Microformats
     * 
     * Converts classic µf classnames to their µf2 equivalents
     */
    public function convertClassic($text) {
        $map = $this->classicMap;

        $text = preg_replace_callback('/class="([a-zA-Z0-9_-]* ?)*"/i', function ($matches) use ($map) {

                    // Replace classic classnames in @class with their µf2 eqivalents
                    $text = $matches[0];

                    // Get just the classes out
                    $text = preg_replace('/class="(.*)"/', '$1', $text);
                    $classnames = explode(' ', $text);

                    foreach ($classnames as $key => $class) {
                        // If there’s a replacement, replace it
                        if (($mf2Class = $map[$class]) != '')
                            $classnames[$key] = $mf2Class;
                    }

                    // Rebuild and return
                    $classString = join(' ', $classnames);
                    return 'class="' . $classString . '"';
                }, $text);

        return $text;
    }

    /**
     * Classic Microformats Map
     * 
     * Maps classic classnames to their µf2 equivalents
     */
    private $classicMap = array(
        // hCard
        'vcard' => 'h-card',
        'fn' => 'p-name',
        'url' => 'u-url',
        'honorific-prefix' => 'p-honorific-prefix',
        'given-name' => 'p-given-name',
        'additional-name' => 'p-additional-name',
        'family-name' => 'p-family-name',
        'honorific-suffix' => 'p-honorific-suffix',
        'nickname' => 'p-nickname',
        'email' => 'u-email',
        'logo' => 'u-logo',
        'photo' => 'u-photo',
        'url' => 'u-url',
        'uid' => 'u-uid',
        'category' => 'p-category',
        'adr' => 'p-adr h-adr',
        'extended-address' => 'p-extended-address',
        'street-address' => 'p-street-address',
        'locality' => 'p-locality',
        'region' => 'p-region',
        'postal-code' => 'p-postal-code',
        'country-name' => 'p-country-name',
        'label' => 'p-label',
        'geo' => 'p-geo h-geo',
        'latitude' => 'p-latitude',
        'longitude' => 'p-longitude',
        'tel' => 'p-tel',
        'note' => 'p-note',
        'bday' => 'dt-bday',
        'key' => 'u-key',
        'org' => 'p-org',
        'organization-name' => 'p-organization-name',
        'organization-unit' => 'p-organization-unit',
        // hAtom
        'hentry' => 'h-entry',
        'entry-title' => 'p-name',
        'entry-summary' => 'p-summary',
        'entry-content' => 'e-content',
        'published' => 'dt-published',
        'updated' => 'dt-updated',
        'author' => 'p-author h-card',
        'category' => 'p-category',
        'geo' => 'p-geo h-geo',
        'latitude' => 'p-latitude',
        'longitude' => 'p-longitude',
        // hRecipe
        'ingredient' => 'p-ingredient',
        'yield' => 'p-yield',
        'instructions' => 'e-instructions',
        'duration' => 'dt-duration',
        'nutrition' => 'p-nutrition',
        // hResume
        'contact' => 'h-card p-contact',
        'education' => 'h-event p-education',
        'experience' => 'h-event p-experience',
        'skill' => 'p-skill',
        'affiliation' => 'p-affiliation h-card'
    );

}

// EOF mf2/Parser.php