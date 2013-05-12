#!/usr/bin/env php
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

function render($states, $actions, $boxed_actions = false)
{
	// DOT Header
	echo	"#\n",
		"# State machine visualization\n",
		"#\n",
		"# Use \"dot -Tpng this-file.dot -o this-file.png\" to compile.\n",
		"#\n",
		"digraph structs {\n",
		"	rankdir = LR;\n",
		"	margin = 0;\n",
		"	bgcolor = transparent;\n",
		"	edge [ arrowtail=none, arrowhead=normal, arrowsize=0.6, fontsize=8 ];\n",
		"	node [ shape=box, fontsize=9, style=\"rounded,filled\", fontname=\"sans\", fillcolor=\"#eeeeee\" ];\n",
		"	graph [ shape=none, color=blueviolet, fontcolor=blueviolet, fontsize=9, fontname=\"sans\" ];\n",
		"\n";

	// Start state
	echo "\t", "BEGIN [\n",
		"label = \"\",",
		"shape = circle,",
		"color = black,",
		"fillcolor = black,",
		"penwidth = 0,",
		"width = 0.25,",
		"style = filled",
		"];\n";

	// States
	echo "\t", "node [ shape=ellipse, fontsize=9, style=\"filled\", fontname=\"sans\", fillcolor=\"#eeeeee\", penwidth=2 ];\n";
	foreach ($states as $s => $state) {
		echo "\t", "s_", $s, " [ label=\"", addcslashes($s, '"'), "\"];\n";
	}

	$have_final_state = false;
	$missing_states = array();

	// Actions
	if ($boxed_actions) {
		echo "\t", "node [ shape=box, fontsize=8, style=\"filled\", fontname=\"sans\", fillcolor=\"#ffffff\", penwidth=1 ];\n";
		foreach ($actions as $a => $action) {
			echo "\t", "a_", $a, " [ label=\"", addcslashes($a, '"'), "\"];\n";
		}
	}

	// Transitions
	$used_actions = array();
	foreach ($actions as $a => $action) {
		$a_a = 'a_'.$a;
		foreach ($action['transitions'] as $src => $transition) {
			if ($src === null || $src === '') {
				$s_src = 'BEGIN';
			} else {
				$s_src = 's_'.$src;
				if (!array_key_exists($src, $states)) {
					$missing_states[$src] = true;
				}
			}
			foreach ($transition['targets'] as $dst) {
				if ($dst === null || $dst === '') {
					$s_dst = 'END';
					$have_final_state = true;
				} else {
					$s_dst = 's_'.$dst;
					if (!array_key_exists($dst, $states)) {
						$missing_states[$dst] = true;
					}
				}
				if ($boxed_actions) {
					if (empty($used_actions[$a][$src])) {
						echo "\t", $s_src, " -> ", $a_a, ";\n";
						$used_actions[$a][$src] = true;
					}
					echo "\t", $a_a, " -> ", $s_dst, ";\n";
				} else {
					echo "\t", $s_src, " -> ", $s_dst, " [ label=\"", addcslashes($a, '"'), "\" ];\n";
				}
			}
		}
	}
	echo "\n";

	// Missing states
	foreach ($missing_states as $s => $state) {
		echo "\t", "s_", $s, " [ label=\"", addcslashes($s, '"'), "\\n(undefined)\", fillcolor=\"#ffccaa\" ];\n";
	}

	// Final state
	if ($have_final_state) {
		echo "\t", "END [\n",
			"label = \"\",",
			"shape = doublecircle,",
			"color = black,",
			"fillcolor = black,",
			"penwidth = 1.8,",
			"width = 0.20,",
			"style = filled",
			"];\n\n";
	}


	// DOT Footer
	echo "}\n";
}

//----------------------------------------------------------------------------

if (count($argv) != 2) {
	error_log("Usage: ".$argv[0]." machine-config.json");
	exit(-1);
}


$machine = json_decode(file_get_contents($argv[1]), TRUE);
if (empty($machine['state_machine'])) {
	error_log('Machine definition not found');
	exit(-1);
}
render($machine['state_machine']['states'], $machine['state_machine']['actions']);

