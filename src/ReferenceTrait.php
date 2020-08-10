<?php declare(strict_types = 1);
/*
 * Copyright (c) 2012-2019, Josef Kufner  <josef@kufner.cz>
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

use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Definition\UndefinedTransitionException;
use Smalldb\StateMachine\Provider\SmalldbProviderInterface;
use Smalldb\StateMachine\ReferenceDataSource\ReferenceDataSourceInterface;
use Smalldb\StateMachine\Transition\TransitionEvent;


/**
 * Trait ReferenceTrait
 *
 * @implements ReferenceInterface
 * @internal
 */
trait ReferenceTrait // implements ReferenceInterface
{
	private Smalldb $smalldb;
	private ?SmalldbProviderInterface $machineProvider = null;
	private ?ReferenceDataSourceInterface $dataSource = null;


	/**
	 * Create a reference and initialize it with a given ID. To copy
	 * a reference use the clone keyword.
	 *
	 * @param Smalldb $smalldb
	 * @param SmalldbProviderInterface|null $machineProvider
	 * @param ReferenceDataSourceInterface $dataSource
	 * @param $id
	 */
	final public function __construct(Smalldb $smalldb, ?SmalldbProviderInterface $machineProvider, ReferenceDataSourceInterface $dataSource, $id = null)
	{
		$this->smalldb = $smalldb;
		$this->machineProvider = $machineProvider;
		$this->dataSource = $dataSource;

		// Do not overwrite $id when it is not provided
		// so that PDOStatement::fetchObject() can provide the value.
		if ($id !== null) {
			$this->setMachineId($id);
		}

		if (($dl = $this->smalldb->getDebugLogger())) {
			$dl->logReferenceCreated($this);
		}
	}


	final protected function getSmalldb(): Smalldb
	{
		return $this->smalldb;
	}


	/**
	 * Lazy-load the provider from Smalldb
	 */
	final protected function getMachineProvider(): SmalldbProviderInterface
	{
		return $this->machineProvider ?? ($this->machineProvider = $this->smalldb->getMachineProvider($this->getMachineType()));
	}


	final protected function getDataSource(): ReferenceDataSourceInterface
	{
		return $this->dataSource;
	}


	/**
	 * Get state machine definition
	 */
	final public function getDefinition(): StateMachineDefinition
	{
		return $this->getMachineProvider()->getDefinition();
	}


	/**
	 * Get machine type
	 *
	 * ReferenceClassGenerator overrides the getDefinition() call with a constant.
	 */
	public function getMachineType(): string
	{
		return $this->getDefinition()->getMachineType();  // @codeCoverageIgnore
	}


	final public function isTransitionAllowed(string $transitionName): bool
	{
		try {
			$provider = $this->getMachineProvider();
			$transition = $provider->getDefinition()->getTransition($transitionName, $this->getState());
			return $provider->getTransitionsDecorator()->isTransitionAllowed($this, $transition);
		}
		catch (UndefinedTransitionException $ex) {
			return false;
		}
	}


	/**
	 * Invoke transition of the state machine.
	 */
	final public function invokeTransition(string $transitionName, ...$args): TransitionEvent
	{
		// TODO: Generate events before and after the transition?
		$this->invalidateCache();

		$transitionEvent = new TransitionEvent($this, $transitionName, $args);
		$this->machineProvider->getTransitionsDecorator()->invokeTransition($transitionEvent, $this->smalldb->getDebugLogger());

		if ($transitionEvent->hasNewId()) {
			$this->setMachineId($transitionEvent->getNewId());
		}

		return $transitionEvent;
	}


	/**
	 * Retrieve a property value by name of the property.
	 *
	 * ReferenceClassGenerator overrides this method with an optimized implementation.
	 *
	 * @codeCoverageIgnore
	 */
	public function get(string $propertyName)
	{
		$property = $this->getDefinition()->getProperty($propertyName);
		$getter = 'get' . ucfirst($property->getName());
		if (method_exists($this, $getter)) {
			return $this->$getter();
		} else {
			throw new RuntimeException(sprintf("Getter not found: %s::%s()" . get_class($this), $getter));
		}
	}

}

