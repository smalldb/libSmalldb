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
 * Reference to one or more state machines. Allows you to invoke transitions in
 * the easy way by calling methods on this reference object. This is syntactic
 * sugar only, nothing really happens here.
 *
 * $id is per machine type unique identifier. It is always a single literal
 * or an array of literals for compound primary keys.
 *
 * Method call on this class invokes the transition.
 *
 * Read-only properties:
 *   - state = $machine->getState($id);
 *   - properties = $machine->getProperties($id);
 *   - ... see __get().
 *
 * Read one property (will load all of them):
 *   $ref['property']
 *
 * Flush property cache:
 *   unset($ref->properties);
 *
 */
class Reference implements \ArrayAccess, \Iterator
{
	protected $machine;
	protected $id;
	protected $state = null;

	protected $properties_cache = null;
	protected $state_cache = null;


	/**
	 * Create reference and initialize it with given ID. To copy
	 * a reference use clone keyword.
	 */
	public function __construct(AbstractMachine $machine, $id)
	{
		$this->machine = $machine;
		$this->id = $id;
	}


	/**
	 * Function call is transition invocation. Just forward it to backend.
	 */
	public function __call($name, $arguments)
	{
		$this->properties = null;
		$r = $this->machine->invokeTransition($this->id, $name, $arguments, $returns);

		switch ($returns) {
			case AbstractMachine::RETURNS_VALUE:
				return $r;
			case AbstractMachine::RETURNS_NEW_ID:
				return new self($this->machine, $r);
			default:
				throw new \RuntimeException('Unknown semantics of the return value: '.$returns);
		}
	}


	/**
	 * Get data from machine
	 */
	public function __get($key)
	{
		switch ($key) {
			case 'id':
				return $this->id;
			case 'machine':
				return $this->machine;
			case 'machineType':
				return $this->machine->getMachineType();
			case 'state':
				if ($this->state_cache !== null) {
					return $this->state_cache;
				} else {
					return ($this->state_cache = $this->machine->getState($this->id));
				}
			case 'properties':
				if ($this->properties_cache !== null) {
					return $this->properties_cache;
				} else {
					return ($this->properties_cache = $this->machine->getProperties($this->id));
				}
			case 'actions':
				return $this->machine->getAvailableTransitions($this->id);
			default:
				throw new \InvalidArgumentException('Unknown property: '.$key);
		}
	}


	/**
	 * Flush cached data.
	 */
	public function __unset($key)
	{
		switch ($key) {
			case 'id':
			case 'machine':
			case 'machineType':
			case 'actions':
				throw new \InvalidArgumentException('Property is not cached: '.$key);
			case 'state':
				$this->state_cache = null;
			case 'properties':
				$this->properties_cache = null;
			default:
				throw new \InvalidArgumentException('Unknown property: '.$key);
		}
	}


	/*
	 * Read cached properties one by one using array access.
	 */

	public function offsetExists($offset)
	{
		if ($this->properties_cache === null) {
			$this->properties_cache = $this->machine->getProperties($this->id);
		}
		return array_key_exists($offset, $this->properties_cache);
	}

	public function offsetGet($offset)
	{
		if ($this->properties_cache === null) {
			$this->properties_cache = $this->machine->getProperties($this->id);
		}
		return $this->properties_cache[$offset];
	}

	public function offsetSet($offset, $value)
	{
		throw new \InvalidArgumentException('Cannot set property: Property cache is read only.');
	}

	public function offsetUnset($offset)
	{
		throw new \InvalidArgumentException('Cannot unset property: Property cache is read only.');
	}

	
	/*
	 * Iterator interface implementation.
	 */

	function rewind() {
		if ($this->properties_cache === null) {
			$this->properties_cache = $this->machine->getProperties($this->id);
		}
		return reset($this->properties_cache);
	}

	function current() {
		if ($this->properties_cache === null) {
			$this->properties_cache = $this->machine->getProperties($this->id);
		}
		return current($this->properties_cache);
	}

	function key() {
		if ($this->properties_cache === null) {
			$this->properties_cache = $this->machine->getProperties($this->id);
		}
		return key($this->properties_cache);
	}

	function next() {
		if ($this->properties_cache === null) {
			$this->properties_cache = $this->machine->getProperties($this->id);
		}
		return next($this->properties_cache);
	}

	function valid() {
		if ($this->properties_cache === null) {
			$this->properties_cache = $this->machine->getProperties($this->id);
		}
		return key($this->properties_cache) !== null;
	}

}

