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
 * Container of all state machines. This is whereReferences come from.
 */
abstract class AbstractBackend
{
	private $alias;
	private $machine_type_cache = array();

	/**
	 * Initialize backend. $alias is used for debugging.
	 */
	public function __construct($alias)
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
	 * Describe given type. Intended as data source for user interface 
	 * generators (menu, navigation, ...).
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
			if ($m === null) {
				throw new \RuntimeException('Machine of type "'.$type.'" cannot be created.');
			}
			$this->machine_type_cache[$type] = $m;
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
	 */
	public function ref($type, $ref)
	{
		$m = $this->getMachine($type);
		return new Reference($m, $ref);
	}

}

