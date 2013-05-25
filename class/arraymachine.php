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

namespace Smalldb;

/**
 * Simple testing machine implementation. Uses array to store all data.
 */
class ArrayMachine extends AbstractMachine
{
	private $machine_definition;

	// Data storage for all state machines
	protected $properties = array();


	/**
	 * Define state machine using $machine_definition.
	 */
	public function initializeMachine($args)
	{
		$this->states  = $args['states'];
		$this->actions = $args['actions'];
	}


	/**
	 * Returns true if user has required permissions.
	 */
	protected function checkPermissions($permissions, $ref)
	{
		return true;
	}


	/**
	 * Get current state of state machine.
	 */
	public function getState($ref)
	{
		if ($ref === null) {
			return '';
		} else {
			return @ $this->properties[$ref]['state'];
		}
	}


	/**
	 * Get all properties of state machine, including it's state.
	 */
	public function getProperties($ref)
	{
		return @ $this->properties[$ref];
	}


	/**
	 * Fake method for all transitions
	 */
	public function __call($method, $args)
	{
		$ref = $args[0];
		$state = $this->getState($ref);

		echo "Transition invoked: ", var_export($state), " (ref = ", var_export($ref), ") -> ",
			get_class($this), "::", $method, "(", join(', ', array_map('var_export', $args)), ")";

		$expected_states = $this->actions[$method]['transitions'][$state]['targets'];

		// create new machine
		if ($ref === null) {
			$ref = count($this->properties);
			echo " [new]";
		}

		$this->properties[$ref]['state'] = $expected_states[0];

		$new_state = $this->getState($ref);
		echo " -> ", var_export($new_state), " (ref = ", var_export($ref), ").\n";

		return $ref;
	}
}

