#!/usr/bin/env php
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

