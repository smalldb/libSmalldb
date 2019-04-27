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


namespace Smalldb\StateMachine\Test\Example\CrudItem;

use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Provider\SmalldbProviderInterface;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\Transition\TransitionEvent;


/**
 * Class CrudItemRef
 * TODO: Implement \Smalldb\StateMachine\Reference ?
 */
class CrudItemRef implements CrudItemMachine
{
	/** @var Smalldb */
	private $smalldb;

	/** @var CrudItemRepository */
	private $repository;

	/** @var SmalldbProviderInterface */
	private $machineProvider;

	private $id;


	public function __construct(Smalldb $smalldb, SmalldbProviderInterface $machineProvider, ...$id)
	{
		$this->smalldb = $smalldb;
		$this->machineProvider = $machineProvider;
		$this->id = $id[0] ?? null;
	}

	public function create($itemData)
	{
		return $this->invokeTransition('create', $itemData);
	}

	public function update($itemData)
	{
		return $this->invokeTransition('update', $itemData);
	}

	public function delete()
	{
		return $this->invokeTransition('delete');
	}

	public function getMachineType(): string
	{
		return $this->machineProvider->getDefinition()->getMachineType();
	}

	/**
	 * Read state machine state
	 */
	public function getState(): string
	{
		return $this->machineProvider->getRepository()->getState($this);
	}

	public function getId(): ?int
	{
		return $this->id;
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
		$transitionEvent = new TransitionEvent($this, $transitionName, $args);
		$transitionEvent->onNewId(function($newId) {
			$this->id = $newId;
		});
		return $this->machineProvider->getTransitionsDecorator()->invokeTransition($transitionEvent);
	}
}
