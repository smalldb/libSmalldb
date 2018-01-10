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

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;


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
	/**
	 * DI Container or service locator from which machines are obtained.
	 *
	 * @var ContainerInterface
	 */
	protected $state_machine_service_locator = null;

	/**
	 * Static table of known machine types. Inherit this class and replace this
	 * table, or use 'machine_types' option when creating this backend.
	 *
	 * Each record is also passed to AbstractMachine::initializeMachine().
	 */
	protected $machine_type_table = array(
		/* '$machine_name' => array(
		 *     'table' => '$database_table',
		 *     'class' => '\SomeNamespace\Class',
		 *  	// Human-friendly name of the type
		 *  	'name' => 'Foo Bar',
		 *  	// Human-friendly description (one short paragraph, plain text)
		 *  	'desc' => 'Lorem ipsum dolor sit amet, ...',
		 *  	// Name of the file containing full machine definition
		 *  	'src'  => 'example/foo.json',
		 */
	);


	/**
	 * Set container which is then used to instantiate state machines.
	 *
	 * @param ContainerInterface $service_locator
	 */
	public function setStateMachineServiceLocator(ContainerInterface $service_locator = null)
	{
		$this->state_machine_service_locator = $service_locator;
	}


	/**
	 * Register new state machine of type $type named $name, which is
	 * instance of class $class. Also additional meta-data can be attached using
	 * $description (will be merged with name, class and args).
	 *
	 * @param string $type  Type to register
	 * @param string $class  State machine implementation. It will be instantiated from the container.
	 * @param array $configuration  Configuration of the state machine.
	 */
	public function registerMachineType(string $type, string $class, array $configuration)
	{
		if (isset($this->machine_type_table[$type])) {
			throw new RuntimeException('Machine type "'.$type.'" is registered already.');
		}

		$configuration['class'] = $class;
		$this->machine_type_table[$type] = $configuration;
	}


	/**
	 * Load all types at once. Argument must be exactly the same as return
	 * value of getKnownTypes method (array of arrays). Useful for loading
	 * types from cache.
	 */
	public function registerAllMachineTypes(array $machine_type_table)
	{
		if (!empty($this->machine_type_table)) {
			throw new RuntimeException('Cannot register all machine types when there are some types registered already.');
		}

		$this->machine_type_table = $machine_type_table;
	}


	/**
	 * @copydoc AbstractBackend::getKnownTypes()
	 */
	public function getKnownTypes()
	{
		return array_keys($this->machine_type_table);
	}


	/**
	 * @copydoc AbstractBackend::describeType()
	 */
	public function describeType($type)
	{
		return $this->machine_type_table[$type] ?? null;
	}


	/**
	 * Infer type of referenced machine type using lookup table.
	 *
	 * Reference is pair: Table name + Primary key.
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
	 * $aref is array of arguments passed to AbstractBackend::ref().
	 *
	 * $type is string.
	 *
	 * $id is literal or array of literals (in case of compound key).
	 */
	public function inferMachineType($aref, & $type, & $id)
	{
		$len = count($aref);

		if ($len == 0) {
			return false;
		}

		$type = str_replace('-', '_', array_shift($aref));

		if (!isset($this->machine_type_table[$type])) {
			return false;
		}

		switch ($len) {
			case 1: $id = null; break;
			case 2: $id = reset($aref); break;
			default: $id = $aref;
		}

		return true;
	}


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
	protected function createMachine(Smalldb $smalldb, string $type)
	{
		if (isset($this->machine_type_table[$type])) {
			$desc = $this->machine_type_table[$type];
		} else {
			return null;
		}

		$class_name = $desc['class'] ?? null;

		if ($class_name === null) {
			throw new InvalidArgumentException('Class not specified in machine configuration.');
		}

		if ($this->state_machine_service_locator && $this->state_machine_service_locator->has($class_name)) {
			$m = $this->state_machine_service_locator->get($class_name);
		} else if (class_exists($class_name)) {
			//debug_msg('Creating machine %s from class: %s', $type, $desc['class']);
			$m = new $class_name();
		} else {
			// Do not know how to create the state machine.
			return null;
		}

		if ($m instanceof AbstractMachine) {
			$m->initializeMachine($smalldb, $type, $desc);
			return $m;
		} else {
			throw new RuntimeException('State machine implementation does not implement '.AbstractMachine::class.'.');
		}
	}



	/**
	 * Creates a listing using given filters.
	 *
	 * @TODO: Support complex filtering over multiple machine types and make
	 * 	'type' filter optional.
	 *
	 * @see AbstractBackend::createListing()
	 */
	protected function createListing(Smalldb $smalldb, $filters, $filtering_flags = 0): IListing
	{
		$type = $filters['type'];
		$machine = $this->getMachine($smalldb, $type);
		if ($machine === null) {
			throw new InvalidArgumentException('Machine type "'.$type.'" not found.');
		}

		// Do not confuse machine-specific filtering
		unset($filters['type']);

		return $machine->createListing($filters, $filtering_flags);
	}

}

