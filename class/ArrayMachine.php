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

namespace Smalldb\StateMachine;

/**
 * Simple testing machine implementation.
 *
 * Uses array to store all data. Any transition is allowed.
 */
class ArrayMachine extends AbstractMachine
{
	private $machine_definition;

	/**
	 * Data storage for all state machines
	 */
	protected $properties_storage = array();


	/**
	 * Reflection: Describe ID (primary key).
	 */
	public function describeId()
	{
		return array('id');
	}


	/**
	 * Returns true if user has required access_policy to invoke a 
	 * transition, which requires given access_policy.
	 */
	protected function checkAccessPolicy($access_policy, Reference $ref)
	{
		return true;
	}


	/**
	 * Adds conditions to enforce read permissions to query object.
	 */
	protected function addPermissionsCondition($query)
	{
	}


	/**
	 * Get current state of state machine.
	 */
	public function getState($id)
	{
		if ($id === null) {
			return '';
		} else {
			return isset($this->properties_storage[$id]['state'])
				? $this->properties_storage[$id]['state']
				: '';
		}
	}


	/**
	 * Get all properties_storage of state machine, including it's state.
	 */
	public function getProperties($id, & $state_cache = null)
	{
		return @ $this->properties_storage[$id];
	}


	/**
	 * Fake method for all transitions
	 */
	public function __call($method, $args)
	{
		$id = $args[0];
		$state = $this->getState($id);

		echo "Transition invoked: ", var_export($state), " (id = ", var_export($id), ") -> ",
			get_class($this), "::", $method, "(", join(', ', array_map('var_export', $args)), ")";

		$expected_states = $this->actions[$method]['transitions'][$state]['targets'];

		// create new machine
		if ($id === null) {
			$id = count($this->properties_storage);
			echo " [new]";
		}

		$this->properties_storage[$id]['state'] = $expected_states[0];

		$new_state = $this->getState($id);
		echo " -> ", var_export($new_state), " (id = ", var_export($id), ").\n";

		return $id;
	}
}

