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
 * Container and factory of all state machines. It creates state machines when
 * required and caches them for further use. Backend also knows what types of
 * state machines can create and knows few useful details about them.
 *
 * This is where \Smalldb\References come from.
 */
abstract class AbstractBackend
{
	private $alias;
	private $machine_type_cache = array();

	/**
	 * Initialize backend. $alias is used for debugging and logging.
	 * Options should contain all required backend-specific data for
	 * backend initialization.
	 */
	public function __construct($alias, $options)
	{
		$this->alias = $alias;
	}


	/**
	 * Get all known state machine types.
	 *
	 * Returns array of strings.
	 */
	public abstract function getKnownTypes();


	/**
	 * Describe given type without creating an instance of related state
	 * machine. Intended as data source for user interface generators
	 * (menu, navigation, ...).
	 *
	 * Returns machine description as propery-value pairs in array. There
	 * are few well-known property names which should be used if possible.
	 * Any unknown properties will be ignored.
	 *
	 * array(
	 *  	// Human-friendly name of the type
	 *  	'name' => 'Foo Bar',
	 *  	// Human-friendly description (one short paragraph, plain text)
	 *  	'desc' => 'Lorem ipsum dolor sit amet, ...',
	 *  	// Name of the file containing full machine definition
	 *  	'src'  => 'example/foo.json',
	 *  	...
	 * )
	 */
	public abstract function describeType($type);


	/**
	 * Infer type of referenced machine type.
	 *
	 * Returns true if decoding is successful, $type and $id are set to
	 * decoded values. Otherwise returns false.
	 *
	 * Since references are global identifier, this method identifies the
	 * type of referenced machine. In simple cases it maps part of ref to
	 * type, in more complex scenarios it may ask database.
	 *
	 * In simple applications ref consists of pair $type and $id, where $id
	 * is uniquie within given $type.
	 *
	 * $aref is array of arguments passed to AbstractBackend::ref() or
	 * single literal if only one argument was passed.
	 *
	 * $type is string.
	 *
	 * $id is literal or array of literals (in case of compound key).
	 */
	public abstract function inferMachineType($aref, & $type, & $id);


	/**
	 * Factory method: Prepare state machine of given type - a model shared
	 * between multiple real statemachines stored in backend. Do not forget
	 * that actual machine is not reachable, you only get this interface.
	 *
	 * This creates only implementation of the machine, not concrete
	 * instance. See AbstractMachine.
	 *
	 * Returns descendant of AbstractMachine or null.
	 */
	protected abstract function createMachine($type);


	/**
	 * Create query builder (like FlupdoBuilder) which shall be used to create
	 * and execute database query to retrieve list of state machine instances
	 * and their properties.
	 */
	abstract public function createQueryBuilder($type);


	/**
	 * Get number of instantiated machines in cache. Useful for statistics
	 * and check whether backend has not been used yet.
	 */
	public function getCachedMachinesCount()
	{
		return count($this->machine_type_cache);
	}


	/**
	 * Get current alias.
	 */
	public function getAlias()
	{
		return $this->alias;
	}


	/**
	 * Get state machine of given type, create it if necessary.
	 */
	public function getMachine($type)
	{
		if (isset($this->machine_type_cache[$type])) {
			return $this->machine_type_cache[$type];
		} else {
			$m = $this->createMachine($type);
			if ($m !== null) {
				$this->machine_type_cache[$type] = $m;
			}
			return $m;
		}
	}


	/**
	 * Describe properties of specified state machine type.
	 */
	public function describe($type)
	{
		$m = $this->getMachine($type);
	}


	/**
	 * Get reference to state machine of given type and id.
	 *
	 * If the first argument is instance of Reference, this makes copy of it.
	 * If the first argument is an array, it will be used instead of all arguments.
	 *
	 * These calls are equivalent:
	 *   $ref = $this->ref('item', 1, 2, 3);
	 *   $ref = $this->ref(array('item', 1, 2, 3));
	 *   $ref = $this->ref($this->ref('item', 1, 2, 3)));
	 */
	public function ref($arg1 /* ... */)
	{
		$argc = func_num_args();

		// Clone if Reference is given
		if ($arg1 instanceof Reference) {
			if ($argc != 1) {
				throw new InvalidArgumentException('The first argument is Reference and more than one argument given.');
			}
			return clone $arg1;
		}

		// Get arguments
		if (is_array($arg1)) {
			if ($argc != 1) {
				throw new InvalidArgumentException('The first argument is array and more than one argument given.');
			}
			$args = $arg1;
			$argc = count($arg1);
		} else {
			$args = func_get_args();
		}

		// Decode arguments to machine type and machine-specific ID
		if (!$this->inferMachineType($args, $type, $id)) {
			throw new InvalidArgumentException('Invalid reference - cannot infer machine type: '.$type);
		}

		// Create reference
		$m = $this->getMachine($type);
		if ($m === null) {
			throw new InvalidArgumentException('Invalid reference - cannot create machine: '.$type);
		}
		return new Reference($m, $id);
	}


	/**
	 * Get reference to non-existent state machine of given type. You may
	 * want to invoke 'create' or similar transition using this reference.
	 */
	public function nullRef($type)
	{
		$m = $this->getMachine($type);
		return new Reference($m, null);
	}


	/**
	 * Flush caches of all machines
	 */
	public function flushCache()
	{
		foreach ($this->machine_type_cache as $t => $m) {
			$m->flushCache();
		}
	}

}

