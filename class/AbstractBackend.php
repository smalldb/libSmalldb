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
	private $machine_type_cache = array();

	/** @var IDebugLogger */
	private $debug_logger = null;
	/** @var Hook */
	private $after_reference_created = null;
	/** @var Hook */
	private $after_listing_created = null;

	private $initialized = false;


	/**
	 * Configure backend using the $configuration.
	 *
	 * This method must be called soon after the instance is created.
	 */
	function initializeBackend(array $config)
	{
		if ($this->initialized) {
			throw new InitializationException("Backend is initialized already.");
		}
		$this->initialized = true;
	}


	/**
	 * Is this backend initialized?
	 *
	 * Note that in the case the initialization has failed, this still may be set to true.
	 */
	public function isBackendInitialized(): bool
	{
		return $this->initialized;
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
	public function getDebugLogger(): IDebugLogger
	{
		return $this->debug_logger;
	}


	/**
	 * Get afterReferenceCreated hook.
	 */
	public function afterReferenceCreated(): Hook
	{
		return $this->after_reference_created ?? ($this->after_reference_created = new Hook());
	}


	/**
	 * Get afterListingCreated hook.
	 */
	public function afterListingCreated(): Hook
	{
		return $this->after_listing_created ?? ($this->after_listing_created = new Hook());
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
	 * @param array|string|int $aref is array of arguments passed to ref() or single literal if only one argument was passed.
	 * @param string $type is a type of the inferred machine.
	 * @param array|string|int $id is an ID of the inferred machine.
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
	 * @return AbstractMachine|null
	 */
	protected abstract function createMachine(Smalldb $smalldb, string $type);


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
	 *
	 * @return AbstractMachine|null
	 */
	public function getMachine(Smalldb $smalldb, string $type)
	{
		if (isset($this->machine_type_cache[$type])) {
			return $this->machine_type_cache[$type];
		} if (!$this->initialized) {
			throw new InitializationException("Backend not initialized.");
		} else {
			$m = $this->machine_type_cache[$type] = $this->createMachine($smalldb, $type);
			if ($m && $this->debug_logger) {
				$m->setDebugLogger($this->debug_logger);
				$this->debug_logger->afterMachineCreated($this, $type, $m);
			}
			return $m;
		}
	}


	/**
	 * Create a listing using given query filters via createListing() method.
	 *
	 * @see createListing()
	 *
	 * @return IListing.
	 */
	public final function listing(Smalldb $smalldb, $query_filters, $filtering_flags = 0)
	{
		$listing = $this->createListing($smalldb, $query_filters, $filtering_flags);

		if ($this->debug_logger) {
			$this->debug_logger->afterListingCreated($this, $listing, $query_filters);
		}
		if ($this->after_listing_created) {
			$this->after_listing_created->emit($listing);
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
	abstract protected function createListing(Smalldb $smalldb, $query_filters, $filtering_flags = 0);


	/**
	 * Perform a quick self-check to detect most common errors (but not all of them).
	 *
	 * This will throw various exceptions on errors.
	 *
	 * @return array Array with the results (machine type -> per-machine results).
	 */
	public function performSelfCheck(Smalldb $smalldb)
	{
		$results = [];

		foreach($this->getKnownTypes() as $m) {
			$machine = $this->getMachine($smalldb, $m);
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
	 * @return string[]
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

