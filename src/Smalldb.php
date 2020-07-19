<?php
/*
 * Copyright (c) 2017-2019, Josef Kufner  <josef@kufner.cz>
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

use Psr\EventDispatcher\EventDispatcherInterface;
use Smalldb\StateMachine\ClassGenerator\GeneratedClassAutoloader;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Provider\SmalldbProviderInterface;
use Smalldb\StateMachine\Transition\TransitionDecorator;


/**
 * The libSmalldb entry point.
 *
 * Smalldb class manages machine providers and uses them to provide
 * a simple lazy way to obtain repositories and references.
 */
class Smalldb
{

	/**
	 * Map of registered machine types and their providers.
	 *
	 * @var SmalldbProviderInterface[]
	 */
	private array $machineProviders = [];

	/**
	 * List of registered machine names without aliases.
	 *
	 * @var string[]
	 */
	private array $machineTypes = [];

	/**
	 * Debug logger that is passed to other Smalldb components.
	 */
	private ?DebugLoggerInterface $debugLogger = null;


	/**
	 * Smalldb constructor.
	 */
	public function __construct()
	{
	}


	/**
	 * Helper method to register autoloader for generated classes when setting up a DI container.
	 */
	public function registerGeneratedClassAutoloader(string $namespace, string $directory, bool $prependAutoloader = false): GeneratedClassAutoloader
	{
		$autoloader = new GeneratedClassAutoloader($namespace, $directory, $prependAutoloader);
		$autoloader->registerLoader();
		return $autoloader;
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
		$this->machineTypes[] = $machineType;

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


	public function getReferenceClass(string $type): string
	{
		return $this->getMachineProvider($type)->getReferenceClass();
	}


	public function getDefinition(string $type): StateMachineDefinition
	{
		return $this->getMachineProvider($type)->getDefinition();
	}


	public function getTransitionsDecorator(string $type): TransitionDecorator
	{
		return $this->getMachineProvider($type)->getTransitionsDecorator();
	}


	public function getRepository(string $type): SmalldbRepositoryInterface
	{
		return $this->getMachineProvider($type)->getRepository();
	}


	/**
	 * Get reference to state machine instance of given type and id.
	 *
	 * @see nullRef()
	 */
	public function ref(string $type, $id): ReferenceInterface
	{
		return $this->getRepository($type)->ref($id);

		// TODO: Emit events
		/*
		if ($this->debug_logger) {
			$this->debug_logger->afterReferenceCreated($this, $machineProvider, $ref);
		}
		if ($this->after_reference_created) {
			$this->after_reference_created->emit($ref);
		}
		*/
	}


	/**
	 * Get reference to non-existent state machine instance of given type. 
	 * You may want to invoke 'create' or similar transition using this 
	 * reference.
	 *
	 * @see ref()
	 */
	public function nullRef(string $type): ReferenceInterface
	{
		return $this->getRepository($type)->ref(null);
	}


	/**
	 * Generate list of all machines.
	 *
	 * @return string[]
	 */
	public function getMachineTypes(): array
	{
		return $this->machineTypes;
	}


	public function setDebugLogger(?DebugLoggerInterface $debugLogger): void
	{
		$this->debugLogger = $debugLogger;
	}


	public function getDebugLogger(): ?DebugLoggerInterface
	{
		return $this->debugLogger;
	}


}

