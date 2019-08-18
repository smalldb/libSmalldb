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
	private $machineProvider;

	/**
	 * Primary key (unique within $machine).
	 * @var mixed
	 */
	protected $id;

	/** @var string */
	private $state = null;

	/** @var mixed */
	private $data = null;


	/**
	 * Create a reference and initialize it with a given ID. To copy
	 * a reference use the clone keyword.
	 */
	public function __construct(Smalldb $smalldb, SmalldbProviderInterface $machineProvider, $id)
	{
		$this->smalldb = $smalldb;
		$this->machineProvider = $machineProvider;
		$this->id = $id;
	}


	/**
	 * Get state machine type.
	 */
	public function getMachineType(): string
	{
		return $this->machineProvider->getDefinition()->getMachineType();
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
	public function getData()
	{
		return $this->data ?? ($this->data = $this->machineProvider->getRepository()->getData($this, $this->state));
	}


	/**
	 * Get state machine definition
	 */
	public function getDefinition(): StateMachineDefinition
	{
		return $this->machineProvider->getDefinition();
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
		$this->data = null;
	}

}

