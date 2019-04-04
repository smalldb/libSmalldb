<?php
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


namespace Smalldb\StateMachine\Definition;

/**
 * Smalldb State Machine Definition -- a non-deterministic persistent finite automaton.
 */
class StateMachineDefinition
{
	/** @var StateDefinition[] */
	private $states;

	/** @var TransitionDefinition[] */
	private $transitions;


	/**
	 * StateMachineDefinition constructor.
	 *
	 * @internal
	 */
	public function __construct(array $states, array $transitions)
	{
		$this->states = $states;
		$this->transitions = $transitions;
	}

	/**
	 * @return StateDefinition[]
	 */
	public function getStates(): array
	{
		return $this->states;
	}

	public function getState(string $name): StateDefinition
	{
		if (isset($this->states[$name])) {
			return $this->states[$name];
		} else {
			throw new UndefinedStateException("Undefined state: $name");
		}
	}

	/**
	 * @return TransitionDefinition[]
	 */
	public function getTransitions(): array
	{
		return $this->transitions;
	}


	public function getTransition(string $sourceState, string $actionName): TransitionDefinition
	{
		// TODO: Add actions support
		if (isset($this->transitions[$actionName])) {
			return $this->transitions[$actionName];
		} else {
			throw new UndefinedTransitionException("Undefined transition: $actionName");
		}
	}

}
