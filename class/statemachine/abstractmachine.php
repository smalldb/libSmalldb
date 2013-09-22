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

namespace Smalldb\StateMachine;

/**
 * Implementation of the state machine. One instance of this class represents
 * all machines of this type.
 *
 * State machine always work with ID, never with Reference. References are
 * decoded within backend.
 */
abstract class AbstractMachine
{
	/**
	 * Return value of invoked transition is just some value.
	 */
	const RETURNS_VALUE = null;

	/**
	 * Return value of invoked transition is new ID of the state machine.
	 */
	const RETURNS_NEW_ID = 'new_id';

	/**
	 * Backend, where all machines are stored.
	 */
	protected $backend;

	/**
	 * Identification within $backend.
	 */
	protected $machine_type;

	/**
	 * Descriptions of all known states -- key is state id, value is * description
	 *
	 * Fill these data in self::initializeMachine().
	 */
	protected $states; /* = array(
		'state_name' => array(
			'label' => _('Human readable name (short)'),
			'description' => _('Human readable description (sentence or two).'),
			'group' => 'state_group_name',	// See $state_groups.
			'color' => '#eeeeee',		// 6 digit hex code (Graphviz and CSS compatible) [optional]
		),
		...
	); */

	/**
	 * State groups. This state machine is flat -- no sub-states. To make
	 * diagrams easier to read, this allows to group relevant states
	 * together. This has no influence on the behaviour.
	 *
	 * Fill these data in self::initializeMachine().
	 */
	protected $state_groups; /* = array(
		'group_name' => array(
			'label' => _('Human readable name (short)'),
			'color' => '#eeeeee',		// 6 digit hex code (Graphviz and CSS compatible) [optional]
			'groups' => array( ... nested state groups ... ),
		),
	); */

	/**
	 * Description of all known actions -- key is action name.
	 *
	 * Each action has transitions (transition function) and each
	 * transition can end in various different states (assertion function).
	 *
	 * Fill these data in self::initializeMachine().
	 */
	protected $actions; /* = array(
		'action_name' => array(
			'label' => _('Human readable name (short)'),
			'description' => _('Human readable description (sentence or two).'),
			'returns' => 'new_id',	// Use this if machine ID is changed after transition. If null, value is returned as is.
			'transitions' => array(
				'source_state' => array(
					'targets' => array('target_state', ... ),
					'method' => 'method_name', // same as action_name if missing
				),
			),
		)
	); */


	/**
	 * Description of machine properties -- key is property name.
	 *
	 * Each property has some metadata available, so it is possible to
	 * generate simple forms or present data to user without writing
	 * per-machine specific templates. These metadata should be
	 * as little implementation specific as possible.
	 */
	protected $properties; /* = array(
		'property_name' => array(
			'label' => _('Human readable name (short)'),
			'description' => _('Human readable description (sentence or two).'),
			'type' => 'type_identifier', // Logical type -- eg. 'price', not 'int'.
			'enum' => array('key' => _('Label'), ...), // Available values for enum types
			'note' => _('Some additional note displayed in forms under the field.'),
		),
	); */


	/**
	 * Constructor. Machine gets reference to owning backend, name of its
	 * type (under which is this machine registered in backend) and
	 * optional array of additional configuration (passed directly
	 * to initializeMachine method).
	 */
	public function __construct(AbstractBackend $backend, $type, $args = array())
	{
		$this->backend = $backend;
		$this->machine_type = $type;
		$this->initializeMachine($args);
	}


	/**
	 * Define state machine used by all instances of this type.
	 */
	abstract protected function initializeMachine($args);


	/**
	 * Returns true if user has required permissions.
	 */
	abstract protected function checkPermissions($permissions, $id);


	/**
	 * Get current state of state machine.
	 */
	abstract public function getState($id);


	/**
	 * Get all properties of state machine, including it's state.
	 */
	abstract public function getProperties($id);


	/**
	 * Reflection: Describe ID (primary key).
	 *
	 * Returns array of all parts of the primary key and its
	 * types (as strings). If primary key is not compound, something
	 * like array('id' => 'string') is returned.
	 *
	 * Order of the parts may be mandatory.
	 */
	abstract public function describeId();


	/**
	 * Get type of this machine.
	 */
	public function getMachineType()
	{
		return $this->machine_type;
	}


	/**
	 * Get mtime of machine implementation.
	 *
	 * Useful to detect outdated cache entry in generated documentation.
	 *
	 * No need to override this method, it handles inherited classes 
	 * correctly. However if machine is loaded from database, a new 
	 * implementation is needed.
	 */
	public function getMachineMTime()
	{
		$reflector = new \ReflectionObject($this);
		return filemtime($reflector->getFilename());
	}


	/**
	 * Reflection: Get all states
	 *
	 * List of can be filtered by section, just like getAllMachineActions 
	 * method does.
	 */
	public function getAllMachineStates($having_section = null)
	{
		if ($having_section === null) {
			return array_keys($this->states);
		} else {
			return array_keys(array_filter($this->states,
				function($a) use ($having_section) { return !empty($a[$having_section]); }));
		}
	}


	/**
	 * Reflection: Describe given machine state
	 *
	 * Returns state description in array or null.
	 */
	public function describeMachineState($state)
	{
		return @ $this->states[$state];
	}


	/**
	 * Reflection: Get all actions (transitions)
	 *
	 * List of actions can be filtered by section defined in action
	 * configuration. For example $this->getAllMachineStates('block') will
	 * return only actions which have 'block' configuration defined.
	 * Requested section must contain non-empty() value.
	 */
	public function getAllMachineActions($having_section = null)
	{
		if ($having_section === null) {
			return array_keys($this->actions);
		} else {
			return array_keys(array_filter($this->actions,
				function($a) use ($having_section) { return !empty($a[$having_section]); }));
		}
	}


	/**
	 * Reflection: Describe given machine action (transition)
	 *
	 * Returns action description in array or null.
	 */
	public function describeMachineAction($action)
	{
		return @ $this->actions[$action];
	}


	/**
	 * Reflection: Get all properties
	 *
	 * List of can be filtered by section, just like getAllMachineActions 
	 * method does.
	 */
	public function getAllMachineProperties($having_section = null)
	{
		if ($having_section === null) {
			return array_keys($this->properties);
		} else {
			return array_keys(array_filter($this->properties,
				function($a) use ($having_section) { return !empty($a[$having_section]); }));
		}
	}


	/**
	 * Reflection: Describe given property
	 *
	 * Returns property description in array or null.
	 */
	public function describeMachineProperty($property)
	{
		return @ $this->properties[$property];
	}


	/**
	 * Get backend which owns this machine.
	 */
	public function getBackend()
	{
		return $this->backend;
	}


	/**
	 * If machine properties are cached, flush all cached data.
	 */
	public function flushCache()
	{
		// No cache
	}


	/**
	 * Get list of all available actions for state machine instance identified by $id.
	 */
	public function getAvailableTransitions($id)
	{
		$state = $this->getState($id);

		$available_transitions = array();

		foreach ($this->actions as $a => $action) {
			$tr = @ $action['transitions'][$state];
			if ($tr !== null) {
				if (!isset($tr['permissions']) || $this->checkPermissions($tr['permissions'], $id)) {
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
	public function invokeTransition($id, $transition_name, $args, & $returns)
	{
		$state = $this->getState($id);

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
		if (!$this->checkPermissions($perms, $id)) {
			throw new \Exception('Access denied to transition "'.$transition_name.'".');
		}

		// get method
		$method = empty($transition['method']) ? $transition_name : $transition['method'];

		// invoke method -- the first argument is $id, rest are $args as passed to $ref->action($args...).
		array_unshift($args, $id);
		$ret = call_user_func_array(array($this, $method), $args);

		// interpret return value
		$returns = @ $action['returns'];
		switch ($returns) {
			case self::RETURNS_VALUE:
				// nop, just pass it back
				break;
			case self::RETURNS_NEW_ID:
				$id = $ret;
				break;
			default:
				throw new \RuntimeException('Unknown semantics of the return value: '.$returns);
		}

		// check result using assertion function
		$new_state = $this->getState($id);
		$target_states = $transition['targets'];
		if (!is_array($target_states)) {
			throw new \Exception('Target state is not defined for transition "'.$transition_name.'" from state "'.$state.'".');
		}
		if (!in_array($new_state, $target_states)) {
			throw new \RuntimeException('State machine ended in unexpected state "'.$new_state
				.'" after transition "'.$transition_name.'" from state "'.$state.'". '
				.'Expected states: '.join(', ', $target_states).'.');
		}

		return $ret;
	}


	/**
	 * Escape string for use as dot identifier.
	 */
	private function escapeDotIdentifier($str)
	{
		return preg_replace('/[^a-zA-Z0-9_]+/', '_', $str).'_'.dechex(0xffff & crc32($str));
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
			"	rankdir = TB;\n",
			"	margin = 0;\n",
			"	bgcolor = transparent;\n",
			"	edge [ arrowtail=none, arrowhead=normal, arrowsize=0.6, fontsize=8 ];\n",
			"	node [ shape=box, fontsize=9, style=\"rounded,filled\", fontname=\"sans\", fillcolor=\"#eeeeee\" ];\n",
			"	graph [ fontsize=9, fontname=\"sans bold\" ];\n",
			"\n";

		// Start state
		echo "\t", "BEGIN [",
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
		$group_content = array();
		if (!empty($this->states)) {
			foreach ($this->states as $s => $state) {
				echo "\t", "s_", $this->escapeDotIdentifier($s),
					" [ label=\"", addcslashes(empty($state['label']) ? $s : $state['label'], '"'), "\"";
				if (!empty($state['color'])) {
					echo ", fillcolor=\"", addcslashes($state['color'], '"'), "\"";
				}
				echo " ];\n";

				if (isset($state['group'])) {
					$group_content[$state['group']][] = $s;
				}
			}
		}

		// State groups
		if (!empty($this->state_groups)) {
			$this->exportDotRenderGroups($this->state_groups, $group_content);
		}

		$have_final_state = false;
		$missing_states = array();

		// Transitions
		$used_actions = array();
		if (!empty($this->actions)) {
			foreach ($this->actions as $a => $action) {
				$a_a = 'a_'.$this->escapeDotIdentifier($a);
				foreach ($action['transitions'] as $src => $transition) {
					if ($src === null || $src === '') {
						$s_src = 'BEGIN';
					} else {
						$s_src = 's_'.$this->escapeDotIdentifier($src);
						if (!array_key_exists($src, $this->states)) {
							$missing_states[$src] = true;
						}
					}
					foreach ($transition['targets'] as $dst) {
						if ($dst === null || $dst === '') {
							$s_dst = 'END';
							$have_final_state = true;
						} else {
							$s_dst = 's_'.$this->escapeDotIdentifier($dst);
							if (!array_key_exists($dst, $this->states)) {
								$missing_states[$dst] = true;
							}
						}
						echo "\t", $s_src, " -> ", $s_dst, " [ ";
						echo "label=\"", addcslashes(empty($action['label']) ? $a : $action['label'], '"'), "\"";
						if (isset($transition['weight'])) {
							echo ", weight=", (int) $transition['weight'];
						}
						echo " ];\n";
					}
				}
			}
			echo "\n";
		}

		// Missing states
		foreach ($missing_states as $s => $state) {
			echo "\t", "s_", $this->escapeDotIdentifier($s), " [ label=\"", addcslashes($s, '"'), "\\n(undefined)\", fillcolor=\"#ffccaa\" ];\n";
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


	/**
	 * Recursively render groups in state machine diagram.
	 */
	private function exportDotRenderGroups($groups, $group_content, $indent = "\t") {
		foreach ($groups as $g => $group) {
			echo $indent, "subgraph cluster_", $this->escapeDotIdentifier($g), " {\n";
			if (isset($group['label'])) {
				echo $indent, "\t", "label = \"", addcslashes($group['label'], '"'), "\";\n";
			}
			if (!empty($group['color'])) {
				echo $indent, "\t", "color=\"", addcslashes($group['color'], '"'), "\";\n";
				echo $indent, "\t", "fontcolor=\"", addcslashes($group['color'], '"'), "\";\n";
			} else {
				// This cannot be defined globally, since nested groups inherit the settings.
				echo $indent, "\t", "color=\"#666666\";\n";
				echo $indent, "\t", "fontcolor=\"#666666\";\n";
			}
			foreach ($group_content[$g] as $s) {
				echo $indent, "\t", "s_", $this->escapeDotIdentifier($s), ";\n";
			}
			if (isset($group['groups'])) {
				$this->exportDotRenderGroups($group['groups'], $group_content, "\t".$indent);
			}
			echo $indent, "}\n";
		}
	}

}

