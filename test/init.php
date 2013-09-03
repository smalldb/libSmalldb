<?php
/*
 * Copyright (c) 2013, Josef Kufner  <jk@frozen-doe.net>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. Neither the name of the author nor the names of its contributors
 *    may be used to endorse or promote products derived from this software
 *    without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE REGENTS AND CONTRIBUTORS ``AS IS'' AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED.  IN NO EVENT SHALL THE REGENTS OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
 * OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
 * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
 * SUCH DAMAGE.
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

