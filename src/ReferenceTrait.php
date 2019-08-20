<?php declare(strict_types = 1);
/*
 * Copyright (c) 2019, Josef Kufner  <josef@kufner.cz>
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
use Smalldb\StateMachine\Transition\TransitionDecorator;
use Smalldb\StateMachine\Transition\TransitionEvent;


/**
 * Trait ReferenceTrait
 *
 * @implements ReferenceInterface
 */
trait ReferenceTrait // implements ReferenceInterface
{
	/** @var Smalldb */
	protected $smalldb;

	/** @var SmalldbProviderInterface */
	private $machineProvider = null;

	/** @var object */
	protected $dataSource = null;

	/**
	 * Primary key (unique within $machine).
	 * @var mixed
	 */
	protected $id;

	/** @var string */
	private $state = null;

	/** @var bool */
	private $dataLoaded = false;


	/**
	 * Create a reference and initialize it with a given ID. To copy
	 * a reference use the clone keyword.
	 */
	public function __construct(Smalldb $smalldb, ?SmalldbProviderInterface $machineProvider, $id = null, $dataSource = null)
	{
		parent::__construct($dataSource);

		$this->smalldb = $smalldb;
		$this->machineProvider = $machineProvider;

		// Do not overwrite $id when it is not provided
		// so that PDOStatement::fetchObject() can provide the value.
		if ($id !== null) {
			$this->id = $id;
		}
	}


	protected function getMachineProvider(): SmalldbProviderInterface
	{
		return $this->machineProvider ?? ($this->machineProvider = $this->smalldb->getMachineProvider($this->getMachineType()));
	}


	/**
	 * Get state machine definition
	 */
	public function getDefinition(): StateMachineDefinition
	{
		return $this->getMachineProvider()->getDefinition();
	}


	/**
	 * Get ID (primary key) of the referred machine
	 */
	public function getId()
	{
		return $this->id;
	}


	/**
	 * Read state machine state
	 */
	public function getState(): string
	{
		return $this->state ?? ($this->state = $this->machineProvider->getRepository()->getState($this));
	}


	/**
	 * Return data from which getState() calculates the state.
	 */
	protected function loadData()
	{
		$data = $this->machineProvider->getRepository()->loadData($this, $this->state);
		if ($data !== null) {
			$this->copyProperties($data);
		}
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
		$oldId = $this->id;

		$this->invalidateCache();

		$transitionEvent = new TransitionEvent($this, $transitionName, $args);
		$transitionEvent->onNewId(function($newId) use ($oldId) {
			$this->id = $newId;

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

	public function invalidateCache(): void
	{
		$this->state = null;
		$this->dataLoaded = false;
	}


}

