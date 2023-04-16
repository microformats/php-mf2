<?php

namespace Mf2\Parser\Test;

use Mf2\Encoding;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;
use function Mf2\load_dom_document;

class EncodingTest extends TestCase {
	/** 
	 * @dataProvider provideContentTypes
	 * @covers Mf2\Encoding::parseContentType
	 */
	public function testParseContentType($in, $type, $charset) {
		$act = Encoding::parseContentType($in);
		$this->assertIsArray($act);
		$this->assertArrayHasKey("type", $act);
		$this->assertArrayHasKey("charset", $act);
		$this->assertSame($type, $act['type'], "Content-Type did not match");
		$this->assertSame($charset, $act['charset'], "Charset did not match");
	}

	public function provideContentTypes() {
		return [
			["a/b",								"a/b",			""],
			["A/B",								"a/b",			""],
			[" \t\r\na/b \t\r\n",				"a/b",			""],
			["a/b ; \t\r\n;",					"a/b",			""],
			["a/b;a=b ; \t\r\n;",				"a/b",			""],
			["a/b;a=b;charset=foo"	,			"a/b",			"foo"],
			["a/b;a=b;charset=foo;charset=bar",	"a/b",			"foo"],
			["a/ b;charset=foo",				"",				""],
			["a/b;charset=FOO",					"a/b",			"FOO"],
			['a/b;charset="foo"',				"a/b",			"foo"],
			['a/b;charset="foo bar"',			"a/b",			"foo bar"],
			['a/b;charset="foo\\"bar"',			"a/b",			'foo"bar'],
			['a/b;a="foo"bar;charset=baz',		"a/b",			'baz'],
			["text/html;charset=UTF-8",         "text/html",	"UTF-8"],
			["",								"",				""],
		];
	}

	/**
	 * @dataProvider provideEncodingLabels
	 * @covers Mf2\Encoding::matchEncodingLabel
	 */
	public function testMatchEncodingLabels($in, $excludeNaugty, $exp) {
		$this->assertSame($exp, Encoding::matchEncodingLabel($in, $excludeNaugty));
	}

	public function provideEncodingLabels() {
		return [
			["",							false, ""],
			["foo",							false, ""],
			["utf8",						false, "UTF-8"],
			["UTF8",						false, "UTF-8"],
			["Unicode",						false, "UTF-16LE"],
			[" \t\r\n\x0Cbig5 \t\r\n\x0C",	false, "Big5"],
			["\v\x00big5\v\x00",			false, ""],
			["iso88591",					false, "windows-1252"],
			["Windows-1252",                false, "windows-1252"],
			["",							true,  ""],
			["foo",							true,  ""],
			["utf8",						true,  "UTF-8"],
			["UTF8",						true,  "UTF-8"],
			["Unicode",						true,  "UTF-16LE"],
			[" \t\r\n\x0Cbig5 \t\r\n\x0C",	true,  ""],
			["\v\x00big5\v\x00",			true,  ""],
			["iso88591",					true,  ""],
			["Windows-1252",                true,  "windows-1252"],
		];
	}

	/**
	 * @dataProvider provideDocuments
	 * @covers Mf2\load_dom_document
	 */
	public function testDetectEncoding($doc, $type, $body, $htmlClass, $headClass) {
		$d = load_dom_document($doc, $type);
		$this->assertInstanceOf("DOMDocument", $d);
		$this->assertNotNull($d->documentElement);
		$this->assertSame($body, $d->documentElement->textContent);
		$this->assertSame($htmlClass, $d->documentElement->getAttribute("class"));
		if ($head = $d->getElementsByTagName("head")->item(0)) {
			$this->assertSame($headClass, $head->getAttribute("class"));
		}
	}

	public function provideDocuments() {
		return [
			["\xEF\xBB\xBF\xC3\xA9",																												"",									"\xC3\xA9",			"",		""],
			["\xC3\xA9",																															"text/html;charset=utf-8",			"\xC3\xA9",			"",		""],
			["<html class=a><head class=b>\xC3\xA9",																								"text/html;charset=utf-8",			"\xC3\xA9",			"a",	"b"],
			["<html class=a foo><head class=b bar=>\xC3\xA9",																						"text/html;charset=utf-8",			"\xC3\xA9",			"a",	"b"],
			["<html class=a><head class=b>\xC3\xA9",																								"text/html;charset=utf-8",			"\xC3\xA9",			"a",	"b"],
			["<html class='a'><head class='b'>\xC3\xA9",																							"text/html;charset=utf-8",			"\xC3\xA9",			"a",	"b"],
			["<html class=\"a\"><head class=\"b\">\xC3\xA9",																						"text/html;charset=utf-8",			"\xC3\xA9",			"a",	"b"],
			["<html class='a'id=c><head class='b'/>\xC3\xA9",																						"text/html;charset=utf-8",			"\xC3\xA9",			"a",	"b"],
			["<html class=a><head class=b>\xC3\xA9",																								"",									"\xC3\xA9",			"a",	"b"],
			["<head class=b><meta charset=koi>\xA7",																								"",									"\xE2\x95\x96",		"",		"b"],
			["<head class=b><meta http-equiv=content-type content=text/html;charset=koi>\xA7",														"",									"\xE2\x95\x96",		"",		"b"],
			["<head class=b><meta content=text/html;charset=koi http-equiv=content-type>\xA7",														"",									"\xE2\x95\x96",		"",		"b"],
			["<head class=b><META HTTP-EQUIV=CONTENT-TYPE CONTENT=text/html;charset=koi>\xA7",														"",									"\xE2\x95\x96",		"",		"b"],
			["\xFE\xFF\xD8\x34\xDD\x1E",																											"",									"\xF0\x9D\x84\x9E",	"",		""],
			["\xFF\xFE\x34\xD8\x1E\xDD",																											"",									"\xF0\x9D\x84\x9E",	"",		""],
			["\xD8\x34\xDD\x1E",																													"text/html;charset=utf-16be",		"\xF0\x9D\x84\x9E",	"",		""],
			["\x34\xD8\x1E\xDD",																													"text/html;charset=utf-16le",		"\xF0\x9D\x84\x9E",	"",		""],
			["<html class=a><head class=b>\x9F",																									"",									"\xC5\xB8",			"a",	"b"],
			["\xC3\xA9",																															"text/html;charset=iso-2022-cn",	"\xEF\xBF\xBD",		"",		""],
			["<html class=a><head class=b>\x9F",																									"",									"\xC5\xB8",			"a",	"b"],
			["<!DOCTYPE><html class=a><head class=b>\xC3\xA9",																						"",									"\xC3\xA9",			"a",	"b"],
			["<!DOCTYPE  ><html class=a><head class=b>\xC3\xA9",																					"",									"\xC3\xA9",			"a",	"b"],
			["<!DOCTYPE html><html class=a><head class=b>\xC3\xA9",																					"",									"\xC3\xA9",			"a",	"b"],
			["<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01//EN\" \"http://www.w3.org/TR/html4/strict.dtd\"><html class=a><head class=b>\xC3\xA9",	"",									"\xC3\xA9",			"a",	"b"],
			[" \n\t<!--><html class=a><head class=b>\xC3\xA9",																						"",									"\xC3\xA9",			"a",	"b"],
			["<html class=a><!-- This is --- a comment! --><head class=b>\xC3\xA9",																	"",									"\xC3\xA9",			"a",	"b"],
			["<html class=a><!-- Pen > Sword --><head class=b>\xC3\xA9",																			"",									"\xC3\xA9",			"a",	"b"],
			["<html class=a><!-- This -> Way --><head class=b>\xC3\xA9",																			"",									"\xC3\xA9",			"a",	"b"],
			["<?xml-stylesheet type=\"text/css\" href=\"style.css\"?><html class=a><head class=b>\xC3\xA9",											"",									"\xC3\xA9",			"a",	"b"],
			["\xDD",																																"text/html;charset=windows-1258",	"\xC6\xAF",			"",		""],
		];
	}
}