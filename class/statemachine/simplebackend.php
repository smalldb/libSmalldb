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

namespace Smalldb\StateMachine;

/**
 * Simple and stupid backend which must be told about everything. Good enough
 * if configuration is loaded by some other part of application from config
 * files, but too dumb to scan database automatically.
 *
 * References are expected to be a pair of $type and $id, where $id is integer
 * or string.
 */
class SimpleBackend extends AbstractBackend
{
	private $known_types = array(
	//	'foo' => array(
	//		'name' => 'Foo',
	//		'class' => '\SomePlugin\FooMachine',
	//		'args' => array(/* additional arguments passed to machine constructor */),
	//	),
	);


	/**
	 * Register new state machine of type $type named $name, which is
	 * instance of class $class. And when creating this machine, pass $args
	 * to its constructor. Also additional meta-data can be attached using
	 * $description (will be merged with name, class and args).
	 */
	public function addType($type, $class, $args = array(), $description = array())
	{
		$this->known_types[$type] = array_merge($description, array(
			'class' => (string) $class,
			'args'  => (array)  $args,
		));
	}


	/**
	 * Load all types at once. Argument must be exactly the same as return
	 * value of getKnownTypes method (array of arrays). Useful for loading
	 * types from cache.
	 */
	public function addAllTypes($known_types)
	{
		if ($this->getCachedMachinesCount() > 0) {
			throw new \RuntimeException('Cannot load all machine types after backend has been used (cache is not empty).');
		}

		if (!empty($this->known_types)) {
			throw new \RuntimeException('Cannot load all machine types when there are some types defined already.');
		}

		$this->known_types = $known_types;
	}


	public function getKnownTypes()
	{
		return array_keys($this->known_types);
	}


	public function describeType($type)
	{
		return $this->known_types[$type];
	}


	public function inferMachineType($ref, & $type, & $id)
	{
		if (!is_array($ref) || count($ref) != 2) {
			throw new \InvalidArgumentException('Invalid reference');
		}

		list($type, $id) = $ref;

		if (isset($this->known_types[$type])) {
			return true;
		} else {
			$type = null;
			$id = null;
			return false;
		}
	}


	protected function createMachine($type)
	{
		if (isset($this->known_types[$type])) {
			$t = $this->known_types[$type];
			return new $t['class']($this, $type, $t['args']);
		} else {
			return null;
		}
	}

}



