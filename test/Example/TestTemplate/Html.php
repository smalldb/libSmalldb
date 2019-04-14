<?php declare(strict_types = 1);
/*
 * Copyright (c) 2019, Josef Kufner  <josef@kufner.cz>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 *     http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */


namespace Smalldb\StateMachine\Test\Example\TestTemplate;


/**
 * Class Html -- a HyperScript-inspired HTML code builder.
 *
 * TODO: Put this into standalone library.
 * TODO: Support all HTML5 elements.
 */
class Html
{
	private static function blockPairElement(string $tag, array $attrs, array $children): string
	{
		if (empty($children)) {
			return "\n" . static::openTag($tag, $attrs) . static::closeTag($tag);
		} else {
			return "\n" . static::openTag($tag, $attrs)
				. join($children)
				. static::closeTag($tag);
		}
	}

	private static function inlinePairElement(string $tag, array $attrs, array $children): string
	{
		return static::openTag($tag, $attrs)
			. join($children)
			. static::closeTag($tag);
	}

	private static function inlineSingleElement(string $tag, array $attrs): string
	{
		return static::openTag($tag, $attrs);
	}

	private static function blockSingleElement(string $tag, array $attrs): string
	{
		return "\n" . static::openTag($tag, $attrs);
	}

	private static function openTag(string $tag, array $attrs): string
	{
		$out = '<' . $tag;
		foreach ($attrs as $k => $v) {
			if (is_array($v) && strncmp($k, 'data-', 5) == 0) {
				$out .= ' ' . $k . '=\''
					. json_encode($v, JSON_NUMERIC_CHECK | JSON_HEX_APOS | JSON_HEX_AMP)
					. '\'';
			} else if ($v !== null) {
				$out .= ' ' . $k . ' ="' . htmlspecialchars($v) . '"';
			}
		}
		$out .= '>';
		return $out;
	}

	private static function closeTag(string $tag): string
	{
		return "</$tag>";
	}

	public static function fragment(...$children): string
	{
		return join($children);
	}

	public static function document(array $attr = [], ...$children): string
	{
		return static::fragment(static::doctype(), static::html($attr, ...$children));
	}

	public static function text(string $raw): string
	{
		return htmlspecialchars($raw);
	}

	public static function doctype(): string
	{
		return "<!DOCTYPE HTML>";
	}

	public static function html(array $attrs = [], ...$children): string
	{
		return static::blockPairElement('html', $attrs, $children);
	}

	public static function head(array $attrs = [], ...$children): string
	{
		return static::blockPairElement('head', $attrs, $children);
	}

	public static function title(array $attrs = [], ...$children): string
	{
		return static::blockPairElement('title', $attrs, $children);
	}

	public static function meta(array $attrs = []): string
	{
		return static::blockSingleElement('meta', $attrs);
	}

	public static function body(array $attrs = [], ...$children): string
	{
		return static::blockPairElement('body', $attrs, $children);
	}

	public static function style(array $attrs = [], ...$children)
	{
		return static::blockPairElement('style', $attrs, $children);
	}

	public static function script(array $attrs = [], ...$children)
	{
		return static::blockPairElement('script', $attrs, $children);
	}

	public static function link(array $attrs = [])
	{
		return static::blockSingleElement('link', $attrs);
	}

	public static function div(array $attrs = [], ...$children): string
	{
		return static::blockPairElement('div', $attrs, $children);
	}

	public static function h1(array $attrs = [], ...$children): string
	{
		return static::blockPairElement('h1', $attrs, $children);
	}

	public static function h2(array $attrs = [], ...$children): string
	{
		return static::blockPairElement('h2', $attrs, $children);
	}

	public static function h3(array $attrs = [], ...$children): string
	{
		return static::blockPairElement('h3', $attrs, $children);
	}

	public static function h4(array $attrs = [], ...$children): string
	{
		return static::blockPairElement('h4', $attrs, $children);
	}

	public static function h5(array $attrs = [], ...$children): string
	{
		return static::blockPairElement('h5', $attrs, $children);
	}

	public static function h6(array $attrs = [], ...$children): string
	{
		return static::blockPairElement('h6', $attrs, $children);
	}

	public static function nav(array $attrs = [], ...$children): string
	{
		return static::blockPairElement('nav', $attrs, $children);
	}

	public static function article(array $attrs = [], ...$children): string
	{
		return static::blockPairElement('article', $attrs, $children);
	}

	public static function p(array $attrs = [], ...$children): string
	{
		return static::blockPairElement('div', $attrs, $children);
	}

	public static function ul(array $attrs = [], ...$children): string
	{
		return static::blockPairElement('ul', $attrs, $children);
	}

	public static function ol(array $attrs = [], ...$children): string
	{
		return static::blockPairElement('ol', $attrs, $children);
	}

	public static function li(array $attrs = [], ...$children): string
	{
		return static::blockPairElement('li', $attrs, $children);
	}

	public static function hr(array $attrs = []): string
	{
		return static::inlineSingleElement('hr', $attrs);
	}

	public static function img(array $attrs = []): string
	{
		return static::inlineSingleElement('img', $attrs);
	}

	public static function svg(array $attrs = [], ...$children): string
	{
		return static::blockPairElement('svg', $attrs, $children);
	}

	public static function canvas(array $attrs = [], ...$children): string
	{
		return static::blockPairElement('canvas', $attrs, $children);
	}

	public static function span(array $attrs = [], ...$children): string
	{
		return static::inlinePairElement('span', $attrs, $children);
	}

	public static function a(array $attrs = [], ...$children): string
	{
		return static::inlinePairElement('a', $attrs, $children);
	}

	public static function em(array $attrs = [], ...$children): string
	{
		return static::inlinePairElement('em', $attrs, $children);
	}

}
