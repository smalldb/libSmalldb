<?php
/*
 * Copyright (c) 2010-2016, Josef Kufner  <josef@kufner.cz>
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

namespace Smalldb\StateMachine;

class Utils
{

	static function template_format($template, $values, $escaping_function = 'htmlspecialchars')
	{
		$available_functions = array(
			'sprintf'	=> 'sprintf',
			'strftime'	=> 'strftime',
			'floor'		=> 'sprintf',
			'ceil'		=> 'sprintf',
			'frac'		=> 'sprintf',
			'frac_str'	=> 'sprintf',
			'intval'	=> 'sprintf',
			'floatval'	=> 'sprintf',
		);

		$tokens = preg_split('/(?:({)'
					."(\\/?[a-zA-Z0-9_.-]+)"			// symbol name
					.'(?:'
						.'([:%])([^:}\s]*)'			// function name
						."(?:([:])((?:[^}\\\\]|\\\\.)*))?"	// format string
					.')?'
					.'(})'
					.'|(\\\\[{}\\\\]))/',
				$template, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

		$status = 0;		// Current status of parser
		$append = 0;		// Append value to result after token is processed ?
		$result = array();
		$process_function = null;
		$format_function = null;

		$raw_values = array_slice(func_get_args(), 3);

		foreach($tokens as $token) {
			switch ($status) {
				// text around
				case 0:
					if ($token === '{') {
						$status = 10;
						$process_function = null;
						$format_function  = null;
						$fmt = null;
					} else if ($token[0] === '\\') {
						$result[] = substr($token, 1);
					} else {
						$result[] = $token;
					}
					break;

				// first part
				case 10:
					$key = $token;
					$status = 20;
					break;

				// first separator
				case 20:
					if ($token === '}') {
						// end
						$append = true;
						$status = 0;
					} else if ($token === '%') {
						$process_function = null;
						$format_function  = 'sprintf';
						$status = 51;
					} else if ($token === ':') {
						$status = 30;
					} else {
						return FALSE;
					}
					break;

				// format function
				case 30:
					if (isset($available_functions[$token])) {
						$process_function = ($token != $available_functions[$token] ? $token : null);
						$format_function  = $available_functions[$token];
					} else {
						$process_function = null;
						$format_function  = null;
					}
					$status = 40;
					break;

				// second separator
				case 40:
					if ($token === ':') {
						$status = 50;
					} else if ($token === '}') {
						$append = true;
						$status = 0;
					} else {
						return FALSE;
					}
					break;

				// format string
				case 50:
					$fmt = preg_replace("/\\\\(.)/", "\\1", $token);
					$status = 90;
					break;

				// format string, prepend %
				case 51:
					$fmt = '%'.str_replace(array('\\\\', '\:', '\}'), array('\\', ':', '}'), $token);
					$status = 90;
					break;

				// end
				case 90:
					if ($token === '}') {
						$append = true;
						$status = 0;
					} else {
						return FALSE;
					}
					break;
			}

			if ($append) {
				$append = false;
				$raw = null;

				// get value
				foreach ($raw_values as $rv) {
					if (isset($rv[$key])) {
						$v = $rv[$key];
						$raw = true;
						break;
					}
				}
				if ($raw === null) {
					if (isset($values[$key])) {
						$v = $values[$key];
						$raw = false;
					} else {
						// key not found, do not append it
						$result[] = '{?'.$key.'?}';
						continue;
					}
				}

				// apply $process_function
				if ($process_function !== null) {
					$v = $process_function($v);
				}

				// apply $format_function
				if ($format_function !== null && $fmt !== null) {
					$v = $format_function($fmt, $v);
				}

				// apply $escaping_function
				if ($escaping_function && !$raw) {
					$v = $escaping_function($v);
				}

				$result[] = $v;
			}
		}
		return join('', $result);
	}


	static function filename_format($template, $values) {
		static $constants = false;
		if ($constants === false) {
			// Fixme: How to invalidate this cache?
			$constants = get_defined_constants();
		}

		$args = func_get_args();
		array_splice($args, 2, 0, array(null, $constants));

		//return template_format($template, $values, null, $constants);
		return call_user_func_array(__NAMESPACE__ . '\Utils::template_format', $args);
	}


	/**
	 * Encode array to JSON using json_encode, but insert PHP snippet to protect 
	 * sensitive data.
	 *
	 * If $filename is set, JSON will be written to given file. Otherwise you are 
	 * expected to store returned string into *.json.php file. 
	 *
	 * Stop snippet: When JSON file is evaluated as PHP, stop snippet will 
	 * interrupt evaluation without breaking JSON syntax, only underscore 
	 * key is appended (and overwritten if exists).
	 *
	 * To make sure that whitelisted keys does not contain PHP tags, all 
	 * occurrences of '<?' are replaced with '<_?' in whitelisted values.
	 *
	 * Default $json_options are:
	 *  - PHP >= 5.4: JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	 *  - PHP <  5.4: JSON_NUMERIC_CHECK
	 *
	 * Options JSON_HEX_TAG and JSON_HEX_APOS are disabled, becouse they break 
	 * PHP snippet.
	 */
	static function write_json_file($filename, $json_array, array $whitelist = null, $json_options = null)
	{
		$stop_snippet = "<?php printf('_%c%c}%c',34,10,10);__halt_compiler();?>";

		if ($whitelist === null) {
			// Put stop snippet on begin.
			$result = array_merge(array('_' => null), $json_array);
		} else {
			// Whitelisted keys first (if they exist in $json_array), then stop snippet, then rest.
			$header = array_intersect_key(array_flip($whitelist), $json_array);
			$header['_'] = null;
			$result = array_merge($header, $json_array);
			// Replace '<?' with '<_?' in all whitelisted values, so injected PHP will not execute.
			foreach ($whitelist as $k) {
				if (array_key_exists($k, $result) && is_string($result[$k])) {
					$result[$k] = str_replace('<?', '<_?', $result[$k]);
				}
			}
		}

		// Put stop snipped at marked position (it is here to prevent 
		// overwriting from $json_array).
		$result['_'] = $stop_snippet;

		$json_str = json_encode($result, $json_options === null
				? (defined('JSON_PRETTY_PRINT')
					? JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
					: JSON_NUMERIC_CHECK)
				: $json_flags & ~(JSON_HEX_TAG | JSON_HEX_APOS));

		if ($filename === null) {
			return $json_str;
		} else {
			return file_put_contents($filename, $json_str);
		}
	}


	/**
	 * JSON version of parse_ini_file().
	 *
	 * Throws JsonException on error.
	 */
	static function parse_json_file($filename)
	{
		$json_str = file_get_contents($filename);
		if ($json_str === FALSE) {
			// FIXME: Use different exception ?
			throw new \Cascade\Core\JsonException("Failed to read file: ".$filename);
		}

		$data = json_decode($json_str, TRUE, 512, JSON_BIGINT_AS_STRING);
		$error = json_last_error();

		if ($error !== JSON_ERROR_NONE) {
			throw new \Cascade\Core\JsonException(json_last_error_msg().' ('.$filename.')', $error);
		}

		return $data;
	}

}


if (!function_exists('json_last_error_msg')) {
/**
 * json_last_error_msg() implementation for PHP < 5.5
 */
function json_last_error_msg()
{
	switch (json_last_error()) {
		default:
			// Other errors are only in PHP >=5.5, where native 
			// implementation of this function is used.
			return null;
		case JSON_ERROR_DEPTH:
			$msg = 'Maximum stack depth exceeded';
			break;
		case JSON_ERROR_STATE_MISMATCH:
			$msg = 'Underflow or the modes mismatch';
			break;
		case JSON_ERROR_CTRL_CHAR:
			$msg = 'Unexpected control character found';
			break;
		case JSON_ERROR_SYNTAX:
			$msg = 'Syntax error, malformed JSON';
			break;
		case JSON_ERROR_UTF8:
			$msg = 'Malformed UTF-8 characters, possibly incorrectly encoded';
			break;
	}
}}

