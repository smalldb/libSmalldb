<?php
/*
 * Copyright (c) 2013, Josef Kufner  <jk@frozen-doe.net>
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

spl_autoload_register(function($class) {
	$dir = dirname(__FILE__).'/../class/';
	$lc_class = strtolower($class);
        @ list($head, $tail) = explode("\\", $lc_class, 2);

	if ($head == 'smalldb') {
		include($dir.str_replace('\\', '/', $tail).'.php');
	}
});


/**
 * Print SQL result in a nice table
 */
function print_table($data)
{
	$col_width = array();

	// pre-calculate column width
	foreach ($data as $row) {
		foreach ($row as $col => $value) {
			$col_width[$col] = max(@$col_width[$col], mb_strlen(var_export($value, true)));
		}
	}

	// include key width
	foreach ($col_width as $label => & $width) {
		$width = max(mb_strlen($label), $width);
	}
	
	// show table header
	echo "\n  ";
	foreach ($col_width as $label => $width) {
		echo "+", str_repeat('-', $width + 2);
	}
	echo "+\n  ";
	foreach ($col_width as $label => $width) {
		$pad = ($width - mb_strlen($label)) / 2.;
		echo "| ", str_repeat(' ', floor($pad)), $label, str_repeat(' ', ceil($pad)), " ";
	}
	echo "|\n  ";
	foreach ($col_width as $label => $width) {
		echo "+", str_repeat('-', $width + 2);
	}
	echo "+\n  ";

	// show table
	foreach ($data as $row) {
		foreach ($row as $col => $value) {
			$value_str = var_export($value, true);
			$pad = $col_width[$col] - mb_strlen($value_str);
			if (is_numeric($value)) {
				echo "| ", str_repeat(' ', $pad), $value_str, " ";
			} else {
				echo "| ", $value_str, str_repeat(' ', $pad), " ";
			}
		}
		echo "|\n  ";
	}

	foreach ($col_width as $label => $width) {
		echo "+", str_repeat('-', $width + 2);
	}
	echo "+\n\n";
}

