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
	/** @var Smalldb */
	private $smalldb;

	/** @var SmalldbProviderInterface */
	private $machineProvider = null;

	/** @var ReferenceDataSourceInterface */
	private $dataSource = null;


	/**
	 * Create a reference and initialize it with a given ID. To copy
	 * a reference use the clone keyword.
	 *
	 * @param Smalldb $smalldb
	 * @param SmalldbProviderInterface|null $machineProvider
	 * @param ReferenceDataSourceInterface $dataSource
	 * @param $id
	 */
	public function __construct(Smalldb $smalldb, ?SmalldbProviderInterface $machineProvider, ReferenceDataSourceInterface $dataSource, $id = null)
	{
		$this->smalldb = $smalldb;
		$this->machineProvider = $machineProvider;
		$this->dataSource = $dataSource;

		// Do not overwrite $id when it is not provided
		// so that PDOStatement::fetchObject() can provide the value.
		if ($id !== null) {
			$this->setMachineId($id);
		}
	}


	protected function getSmalldb(): Smalldb
	{
		return $this->smalldb;
	}


	/**
	 * Lazy-load the provider from Smalldb
	 */
	protected function getMachineProvider(): SmalldbProviderInterface
	{
		return $this->machineProvider ?? ($this->machineProvider = $this->smalldb->getMachineProvider($this->getMachineType()));
	}


	protected function getDataSource(): ReferenceDataSourceInterface
	{
		return $this->dataSource;
	}


	/**
	 * Get state machine definition
	 */
	public function getDefinition(): StateMachineDefinition
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


	/**
	 * Invoke transition of the state machine.
	 */
	public function invokeTransition(string $transitionName, ...$args)
	{
		// TODO: Hooks ?
		//if ($this->before_transition) {
		//	$this->before_transition->emit($this, $transitionName, $args);
		//}
		$oldId = $this->getMachineId();

		$this->invalidateCache();

		$transitionEvent = new TransitionEvent($this, $transitionName, $args);
		$transitionEvent->onNewId(function($newId) use ($oldId) {
			$this->setMachineId($newId);

			//if ($this->after_pk_changed) {
			//	$this->after_pk_changed->emit($this, $oldId, $this->id);
			//}
		});
		$this->machineProvider->getTransitionsDecorator()->invokeTransition($transitionEvent);

		//if ($this->after_transition) {
		//	$this->after_transition->emit($this, $transitionName, $args, $transitionEvent);
		//}

		return $transitionEvent;
	}

}

