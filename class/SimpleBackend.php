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
			throw new RuntimeException('Cannot load all machine types after backend has been used (cache is not empty).');
		}

		if (!empty($this->known_types)) {
			throw new RuntimeException('Cannot load all machine types when there are some types defined already.');
		}

		$this->known_types = $known_types;
	}


	/**
	 * @copydoc AbstractBackend::getKnownTypes()
	 */
	public function getKnownTypes()
	{
		return array_keys($this->known_types);
	}


	/**
	 * @copydoc AbstractBackend::describeType()
	 */
	public function describeType($type)
	{
		return $this->known_types[$type];
	}


	/**
	 * @copydoc AbstractBackend::inferMachineType()
	 */
	public function inferMachineType($aref, & $type, & $id)
	{
		if (!is_array($aref) || count($aref) != 2) {
			throw new InvalidArgumentException('Invalid reference');
		}

		list($type, $id) = $aref;

		if (isset($this->known_types[$type])) {
			return true;
		} else {
			$type = null;
			$id = null;
			return false;
		}
	}


	/**
	 * @copydoc AbstractBackend::createMachine()
	 */
	protected function createMachine($type)
	{
		if (isset($this->known_types[$type])) {
			$t = $this->known_types[$type];
			return new $t['class']($this, $type, $t['args']);
		} else {
			return null;
		}
	}


	/**
	 * @copydoc AbstractBackend::createListing()
	 */
	protected function createListing($query_filters, $filtering_flags = 0)
	{
		throw new \Exception('Not implemented.');
	}

}

