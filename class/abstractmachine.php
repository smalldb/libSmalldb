<?php
/*
 * Copyright (c) 2012, Josef Kufner  <jk@frozen-doe.net>
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

namespace Smalldb;

/**
 * Implementation of the state machine. One instance of this class represents 
 * all machines of this type.
 */
abstract class AbstractMachine
{
	/**
	 * Backend, where all machines are stored.
	 */
	protected $backend;

	/**
	 * Descriptions of all known states -- key is state id, value is * description
	 */
	protected $states /* = array(
		'state_name' => array(
			'label' => 'Human readable name (short)',
			'description' => 'Human readable description (sentence or two).',
			'color' => '#eeeeee',		// 6 digit hex code (Graphviz and CSS compatible) [optional]
		),
		...
	); */

	/**
	 * Description of all known actions -- key is action name.
	 *
	 * Each action has transitions (transition function) and each 
	 * transition can end in various different states (assertion function).
	 */
	protected $actions /* = array(
		'action_name' => array(
			'label' => 'Human readable name (short)',
			'description' => 'Human readable description (sentence or two).',
			'transitions' => array(
				'source_state' => array(
					'targets' => array('target_state', ... ),
					'method' => 'method_name', // same as action_name if missing
				),
			),
		)
	); */

	
	public function __construct(AbstractBackend $backend)
	{
		$this->backend = $backend;
		$this->initializeMachine();
	}


	/**
	 * Define state machine used by all instances of this type.
	 */
	abstract protected function initializeMachine();


	/**
	 * Returns true if user has required permissions.
	 */
	abstract protected function checkPermissions($permissions, $ref);


	/**
	 * Get current state of state machine.
	 */
	abstract public function getState($ref);

	/**
	 * Get all properties of state machine, including it's state.
	 */
	abstract public function getProperties($ref);


	/**
	 * Get list of all available actions for $ref.
	 */
	public function getAvailableTransitions($ref)
	{
		$state = $this->getState($ref);

		$available_transitions = array();

		foreach ($this->actions as $a => $action) {
			$tr = @ $action['transitions'][$state];
			if ($tr !== null) {
				if (!isset($tr['permissions']) || $this->checkPermissions($tr['permissions'], $ref)) {
					$available_transitions[$a] = $tr;
				}
			}
		}

		return $available_transitions;
	}


	/**
	 * Invoke state machine transition. State machine is not instance of 
	 * this class, but it is represented by record in database.
	 */
	public function invokeTransition($ref, $transition_name, $args)
	{
		$state = $this->getState($ref);

		// get action
		$action = @ $this->actions[$transition_name];
		if ($action === null) {
			throw new \DomainException('Unknown transition requested: '.$transition_name);
		}

		// get transition (instance of action)
		$transition = @ $action['transitions'][$state];
		if ($transition === null) {
			throw new \DomainException('Transition "'.$transition_name.'" not found in state "'.$state.'".');
		}

		// check permissions
		$perms = @ $transition['permissions'];
		if (!$this->checkPermissions($transition['permissions'], $ref)) {
			throw new \Exception('Access denied to transition "'.$transition_name.'".');
		}

		// get method
		$method = empty($transition['method']) ? $action : $transition['method'];

		// invoke method -- the first argument is $ref, rest are $args as passed to $ref->action($args...).
		array_unshift($args, $ref);
		$ret = call_user_func_array(array($this, $method), $args);

		// check result using assertion function
		$new_state = $this->getState($ref);
		if (!in_array($new_state, $transition['target_state'])) {
			throw new \RuntimeException('State machine ended in unexpected state "'.$new_state
				.'" after transition "'.$transition_name.'" from state "'.$state.'".');
		}

		return $ret;
	}


	/**
	 * Export state machine to Graphviz source code.
	 */
	public function exportDot()
	{
		ob_start();

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
		foreach ($this->states as $s => $state) {
			echo "\t", "s_", $s, " [ label=\"", addcslashes(empty($state['label']) ? $s : $s['label'], '"'), "\"";
			if (!empty($state['color'])) {
				echo ", fillcolor=\"", addcslashes($state['color'], '"'), "\"";
			}
			echo " ];\n";
		}

		$have_final_state = false;
		$missing_states = array();

		// Transitions
		$used_actions = array();
		foreach ($this->actions as $a => $action) {
			$a_a = 'a_'.$a;
			foreach ($action['transitions'] as $src => $transition) {
				if ($src === null || $src === '') {
					$s_src = 'BEGIN';
				} else {
					$s_src = 's_'.$src;
					if (!array_key_exists($src, $this->states)) {
						$missing_states[$src] = true;
					}
				}
				foreach ($transition['targets'] as $dst) {
					if ($dst === null || $dst === '') {
						$s_dst = 'END';
						$have_final_state = true;
					} else {
						$s_dst = 's_'.$dst;
						if (!array_key_exists($dst, $this->states)) {
							$missing_states[$dst] = true;
						}
					}
					echo "\t", $s_src, " -> ", $s_dst,
						" [ label=\"", addcslashes(empty($action['label']) ? $a : $action['label'], '"'), "\" ];\n";
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

		return ob_get_clean();
	}

}

