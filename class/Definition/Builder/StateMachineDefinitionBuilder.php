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

namespace Smalldb\StateMachine\Definition\Builder;

use Smalldb\StateMachine\Definition\ActionDefinition;
use Smalldb\StateMachine\Definition\StateDefinition;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Definition\TransitionDefinition;
use Smalldb\StateMachine\Definition\UndefinedStateException;


class StateMachineDefinitionBuilder
{
	/** @var StatePlaceholder[] */
	private $states = [];

	/** @var ActionPlaceholder[] */
	private $actions = [];

	/** @var TransitionPlaceholder[] */
	private $transitions = [];

	/** @var TransitionPlaceholder[][] */
	private $transitionsByState = [];


	public function __construct()
	{
		$this->addState('');
	}


	public function build(): StateMachineDefinition
	{
		/** @var StateDefinition[] $states */
		$states = [];
		foreach ($this->states as $statePlaceholder) {
			$state = $this->buildStateDefinition($statePlaceholder);
			$states[$state->getName()] = $state;
		}

		/** @var TransitionDefinition[] $transitions */
		$transitions = [];
		$transitionsByAction = [];
		foreach ($this->transitions as $transitionPlaceholder) {
			$transition = $this->buildTransitionDefinition($transitionPlaceholder, $states);
			$transitions[] = $transition;

			$transitionName = $transition->getName();
			$sourceStateName = $transition->getSourceState()->getName();
			$transitionsByAction[$transitionName][$sourceStateName] = $transition;
		}

		/** @var ActionDefinition[] $actions */
		$actions = [];
		foreach ($this->actions as $actionPlaceholder) {
			$action = $this->buildActionDefinition($actionPlaceholder, $transitionsByAction[$actionPlaceholder->name]);
			$actions[$action->getName()] = $action;
		}

		return new StateMachineDefinition($states, $actions, $transitions);
	}

	protected function buildStateDefinition(StatePlaceholder $statePlaceholder): StateDefinition
	{
		return new StateDefinition($statePlaceholder->name);
	}


	protected function buildActionDefinition(ActionPlaceholder $actionPlaceholder, array $transitions): ActionDefinition
	{
		return new ActionDefinition($actionPlaceholder->name, $transitions);
	}


	protected function buildTransitionDefinition(TransitionPlaceholder $transitionPlaceholder, array $stateDefinitions): TransitionDefinition
	{
		$sourceState = $stateDefinitions[$transitionPlaceholder->sourceState] ?? null;
		if ($sourceState === null) {
			throw new UndefinedStateException("Undefined source state \"$transitionPlaceholder->sourceState\" in transition \"$transitionPlaceholder->name\".");
		}

		$targetStates = [];
		foreach ($transitionPlaceholder->targetStates as $targetStateName) {
			$targetState = $stateDefinitions[$targetStateName] ?? null;
			if ($targetState === null) {
				throw new UndefinedStateException("Undefined target state \"$targetStateName\" in transition \"$transitionPlaceholder->name\".");
			}
			$targetStates[$targetStateName] = $targetState;
		}

		return new TransitionDefinition($transitionPlaceholder->name, $sourceState, $targetStates);
	}


	public function addState(string $name)
	{
		if (isset($this->states[$name])) {
			throw new DuplicateStateException("State already exists: $name");
		} else {
			$this->states[$name] = new StatePlaceholder($name);
		}
		return $this;
	}


	public function addAction(string $name)
	{
		if (isset($this->actions[$name])) {
			throw new DuplicateActionException("Action already exists: $name");
		} else {
			$this->actions[$name] = new ActionPlaceholder($name);
		}
		return $this;
	}


	public function addTransition(string $transitionName, string $sourceStateName, array $targetStateNames)
	{
		if (isset($this->transitionsByState[$sourceStateName][$transitionName])) {
			throw new DuplicateTransitionException("Transition \"$transitionName\" already exists in state \"$sourceStateName\".");
		} else {
			if (empty($this->actions[$transitionName])) {
				$this->addAction($transitionName);
			}

			$placeholder = new TransitionPlaceholder($transitionName, $sourceStateName, $targetStateNames);
			$this->transitionsByState[$sourceStateName][$transitionName] = $placeholder;
			$this->transitions[] = $placeholder;
		}
		return $this;
	}

}
