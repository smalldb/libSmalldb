<?php
/*
 * Copyright (c) 2012, Josef Kufner  <jk@frozen-doe.net>
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

use Smalldb\StateMachine\Utils\Hook;


/**
 * Container and factory of all state machines.
 *
 * It creates state machines when required and caches them for further use. 
 * Backend also knows what types of state machines can create and knows few 
 * useful details about them.
 *
 * This is where References come from.
 */
abstract class AbstractBackend
{
	private $alias;
	private $context = null;
	private $machine_type_cache = array();
	private $debug_logger = null;
	private $after_reference_created = null;
	private $after_listing_created = null;


	/**
	 * Initialize backend. $alias is used for debugging and logging.
	 * Options should contain all required backend-specific data for
	 * backend initialization.
	 *
	 * @param $options are used by derived classes to configure everything.
	 * @param $context is accessible to AbstractMachine, but it may not be
	 * 	available in constructor - see setContext().
	 * @param $alias is used to identify instances in log.
	 */
	public function __construct($options, $context = null, $alias)
	{
		$this->alias = $alias;

		if ($context !== null) {
			$this->setContext($context);
		}
	}


	/**
	 * Set debug logger
	 */
	public function setDebugLogger(IDebugLogger $debug_logger)
	{
		$this->debug_logger = $debug_logger;

		if ($this->debug_logger) {
			$this->debug_logger->afterDebugLoggerRegistered($this);
		}
	}


	/**
	 * Get debug logger
	 */
	public function getDebugLogger()
	{
		return $this->debug_logger;
	}


	/**
	 * Get current alias.
	 */
	public function getAlias()
	{
		return $this->alias;
	}


	/**
	 * Get afterReferenceCreated hook.
	 */
	public function afterReferenceCreated()
	{
		return $this->after_reference_created ?? ($this->after_reference_created = new Hook());
	}


	/**
	 * Get afterListingCreated hook.
	 */
	public function afterListingCreated()
	{
		return $this->after_listing_created ?? ($this->after_listing_created = new Hook());
	}


	/**
	 * Set context if not set yet.
	 *
	 * Context cannot be changed later, but to 
	 */
	public function setContext($context)
	{
		if ($this->context !== null) {
			throw new RuntimeException('Context is already set.');
		}

		$this->context = $context;
	}


	/**
	 * Get context object (whatever it is).
	 *
	 * @param $resource_name Resource of the context to return.
	 * @return Return whole context if `$resource` is null, otherwise returns
	 * 	requested resource from the context.
	 * @see AbstractMachine::getContext()
	 */
	public function getContext($resource_name = null)
	{
		if ($resource_name === null) {
			return $this->context;
		} else {
			return is_array($this->context)
				? $this->context[$resource_name]
				: $this->context->$resource_name;
		}
	}


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
	 * $aref is array of arguments passed to ref() or single literal if 
	 * only one argument was passed.
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
	 * Get number of instantiated machines in cache. Useful for statistics
	 * and check whether backend has not been used yet.
	 */
	public function getCachedMachinesCount()
	{
		return count($this->machine_type_cache);
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
			if ($this->debug_logger) {
				$m->setDebugLogger($this->debug_logger);
				$this->debug_logger->afterMachineCreated($this, $type, $m);
			}
			if ($m !== null) {
				$this->machine_type_cache[$type] = $m;
			}
			return $m;
		}
	}


	/**
	 * Get reference to state machine instance of given type and id.
	 *
	 * If the first argument is instance of Reference, this makes copy of it.
	 * If the first argument is an array, it will be used instead of all arguments.
	 *
	 * These calls are equivalent:
	 *
	 *     $ref = $this->ref('item', 1, 2, 3);
	 *     $ref = $this->ref(array('item', 1, 2, 3));
	 *     $ref = $this->ref($this->ref('item', 1, 2, 3)));
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
			throw new InvalidReferenceException('Cannot infer machine type: '.$type);
		}

		// Create reference
		$m = $this->getMachine($type);
		if ($m === null) {
			throw new RuntimeException('Cannot create machine: '.$type);
		}
		$ref = new Reference($m, $id);

		// Emit events
		if ($this->debug_logger) {
			$this->debug_logger->afterReferenceCreated($this, $ref);
		}
		if ($this->after_reference_created) {
			$this->after_reference_created->emit($ref);
		}

		return $ref;
	}


	/**
	 * Get reference to non-existent state machine instance of given type. 
	 * You may want to invoke 'create' or similar transition using this 
	 * reference.
	 */
	public function nullRef($type)
	{
		$m = $this->getMachine($type);
		if ($m === null) {
			throw new RuntimeException('Cannot create machine: '.$type);
		}
		$ref = new Reference($m, null);

		// Emit events
		if ($this->debug_logger) {
			$this->debug_logger->afterReferenceCreated($this, $ref);
		}
		if ($this->after_reference_created) {
			$this->after_reference_created->emit($ref);
		}

		return $ref;
	}


	/**
	 * Create a listing using given query filters via createListing() method.
	 *
	 * @see createListing()
	 *
	 * @return IListing.
	 */
	public final function listing($query_filters, $filtering_flags = 0)
	{
		$listing = $this->createListing($query_filters, $filtering_flags);

		if ($this->debug_logger) {
			$this->debug_logger->afterListingCreated($this, $listing, $query_filters);
		}
		if ($this->after_listing_created) {
			$this->after_listing_created->emit($ref);
		}

		return $listing;
	}


	/**
	 * Create a listing using given query filters.
	 *
	 * Listing class is inferred from the query filters, callers should not 
	 * expect any particular class to be used. This allows to replace 
	 * listing classes at any time without breaking anything.
	 *
	 * This method does not perform any query on its own, it only 
	 * determines which query should be done and returns appropriate 
	 * listing object.
	 *
	 * @return IListing.
	 */
	abstract protected function createListing($query_filters, $filtering_flags = 0);


	/**
	 * Perform a quick self-check to detect most common errors (but not all of them).
	 *
	 * This will throw various exceptions on errors.
	 *
	 * @return Array with results (machine type -> per-machine results).
	 */
	public function performSelfCheck()
	{
		$results = [];

		foreach($this->getKnownTypes() as $m) {
			$machine = $this->getMachine($m);
			$results[$m] = $machine->performSelfCheck();
		}

		return $results;
	}


	/******************************************************************//**
	 *
	 * \name	Reflection API
	 *
	 * @{
	 */


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
	 *     array(
	 *      	// Human-friendly name of the type
	 *      	'name' => 'Foo Bar',
	 *      	// Human-friendly description (one short paragraph, plain text)
	 *      	'desc' => 'Lorem ipsum dolor sit amet, ...',
	 *      	// Name of the file containing full machine definition
	 *      	'src'  => 'example/foo.json',
	 *      	...
	 *     )
	 */
	public abstract function describeType($type);


	/** @} */

}

