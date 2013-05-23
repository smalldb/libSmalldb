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
 * Reference to one or more state machines. Allows you to invoke transitions in 
 * the easy way by calling methods on this reference object. This is syntactic 
 * sugar only, nothing really happen here.
 *
 * Method call on this class invokes the transition.
 *
 * Read-only properties:
 *   - state = $machine->getState($ref);
 *   - properties = $machine->getProperties($ref);
 */
class Reference 
{
	protected $ref;
	protected $machine;


	/**
	 * Create reference and initialize it with given primary key or other reference.
	 */
	public function __construct($machine, $ref)
	{
		$this->machine = $machine;

		if ($ref instanceof self) {
			$this->ref = $ref->ref;
		} else {
			$this->ref = $ref;
		}
	}


	/**
	 * Get data from machine
	 */
	public function __get($key)
	{
		switch ($key) {
			case 'ref':
				return $this->ref;
			case 'machine':
				return $this->machine;
			case 'state':
				return $this->machine->getState($this->ref);
			case 'properties':
				return $this->machine->getProperties($this->ref);
			case 'actions':
				return $this->machine->getAvailableTransitions($this->ref);
		}
	}


	/**
	 * Function call is transition invocation. Just forward it to backend.
	 */
	public function __call($name, $arguments)
	{
		return $this->machine->invokeTransition($this->ref, $name, $arguments);
	}

	// todo: what about array_map, reduce, walk, ... ?
}

