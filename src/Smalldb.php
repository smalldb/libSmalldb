<?php
/*
 * Copyright (c) 2017, Josef Kufner  <josef@kufner.cz>
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

use Smalldb\StateMachine\Provider\SmalldbProviderInterface;
use Smalldb\StateMachine\Utils\Hook;


/**
 * The libSmalldb entry point.
 * 
 * Smalldb class manages backends (see AbstractBackend) and provides API to
 * create listings and references from the backends.
 */
class Smalldb
{
	/** @var IDebugLogger */
	private $debug_logger = null;

	/** @var Hook */
	private $after_reference_created = null;

	/** @var Hook */
	private $after_listing_created = null;

	/**
	 * Map of registered machine types and their providers.
	 *
	 * @var SmalldbProviderInterface[]
	 */
	private $machineProviders = [];

	/**
	 * List of registered backends
	 * @var AbstractBackend[]
	 */
	protected $backends = [];


	/**
	 * Smalldb constructor.
	 */
	public function __construct()
	{
	}


	/**
	 * Set debug logger
	 */
	public function setDebugLogger(IDebugLogger $debug_logger)
	{
		$this->debug_logger = $debug_logger;
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
	 * Register machine type and its provider
	 *
	 * @param SmalldbProviderInterface $provider
	 * @param string[] $aliases
	 */
	public function registerMachineType(SmalldbProviderInterface $provider, array $aliases = [])
	{
		$machineType = $provider->getMachineType();
		if (isset($this->machineProviders[$machineType])) {
			throw new InvalidArgumentException('Duplicate machine type: ' . $machineType);
		}
		$this->machineProviders[$machineType] = $provider;

		foreach ($aliases as $alias) {
			if (isset($this->machineProviders[$alias])) {
				throw new InvalidArgumentException('Duplicate machine type (alias): ' . $alias);
			}
			$this->machineProviders[$alias] = $provider;
		}
	}


	/**
	 * Retrieve a machine provider for the given machine type or reference class.
	 */
	public function getMachineProvider(string $machineType): SmalldbProviderInterface
	{
		if (isset($this->machineProviders[$machineType])) {
			return $this->machineProviders[$machineType];
		} else {
			throw new InvalidArgumentException('Undefined machine type: ' . $machineType);
		}
	}


	/**
	 * Register a new backend
	 */
	public function registerBackend(AbstractBackend $backend)
	{
		if (in_array($backend, $this->backends, TRUE)) {
			throw new InvalidArgumentException('Duplicate backend: '.get_class($backend));
		} else {
			$this->backends[] = $backend;

			if ($this->debug_logger) {
				$backend->setDebugLogger($this->debug_logger);
			}
		}
		return $this;
	}


	/**
	 * Get list of registered backends
	 *
	 * @return AbstractBackend[]
	 */
	public function getBackends(): array
	{
		return $this->backends;
	}


	/**
	 * Obtain machine from backends.
	 *
	 * @return AbstractMachine|null
	 */
	public function getMachine(string $type)
	{
		foreach ($this->backends as $b => $backend) {
			$m = $backend->getMachine($this, $type);
			if ($m !== null) {
				return $m;
			}
		}
		return null;
	}


	/**
	 * Get reference to state machine instance of given type and id.
	 */
	public function ref(string $type, $id): ReferenceInterface
	{
		// Create the Reference
		$machineProvider = $this->getMachineProvider($type);
		$refClass = $machineProvider->getReferenceClass();
		/** @var ReferenceInterface $ref */
		$ref = new $refClass($id);
		$ref->smalldbConnect($this, $machineProvider);

		// Emit events
		if ($this->debug_logger) {
			$this->debug_logger->afterReferenceCreated($this, $machineProvider, $ref);
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
	public function nullRef(string $type): ReferenceInterface
	{
		return $this->ref($type, null);
	}


	/**
	 * Create a listing using given query filters via createListing() method.
	 *
	 * @see createListing()
	 *
	 * @return IListing.
	 */
	public final function listing($query_filters, $filtering_flags = 0): IListing
	{
		foreach ($this->backends as $b => $backend) {
			$listing = $backend->listing($this, $query_filters, $filtering_flags);

			if ($listing) {
				//if ($this->debug_logger) {
				//	$this->debug_logger->afterListingCreated($backend, $listing, $query_filters);
				//}
				if ($this->after_listing_created) {
					$this->after_listing_created->emit($listing);
				}

				return $listing;
			}
		}
		throw new RuntimeException('Cannot create listing.');
	}


	/**
	 * Generate list of all machines.
	 */
	public function getAllMachines()
	{
		foreach ($this->backends as $b => $backend) {
			foreach ($backend->getKnownTypes() as $m) {
				yield $m => $backend->getMachine($this, $m);
			}
		}
		return;
	}


	/**
	 * Perform a quick self-check to detect most common errors (but not all of them).
	 *
	 * This will throw various exceptions on errors.
	 *
	 * @return array  Array with results (machine type -> per-machine results).
	 */
	public function performSelfCheck(): array
	{
		$results = [];

		foreach ($this->backends as $b => $backend) {
			foreach($backend->getKnownTypes() as $m) {
				$machine = $backend->getMachine($this, $m);
				$results[$b][$m] = $machine->performSelfCheck();
			}
		}

		return $results;
	}

}

