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


namespace Smalldb\StateMachine\Definition;

use Smalldb\StateMachine\Definition\StateMachineGraph\StateMachineGraph;
use Smalldb\StateMachine\Definition\StateMachineGraph\StateMachineNode;
use Smalldb\StateMachine\Graph\GraphSearch;
use Smalldb\StateMachine\Graph\Node;

/**
 * Smalldb State Machine Definition -- a non-deterministic persistent finite automaton.
 *
 * The state machine consists of states, actions and transitions.
 * A transition has one source state and many target states. Actions group
 * transitions of the same name; an action name is an input symbol of
 * the machine.
 */
class StateMachineDefinition
{
	/** @var string */
	private $machineType;

	/** @var StateDefinition[] */
	private $states;

	/** @var ActionDefinition[] */
	private $actions;

	/** @var TransitionDefinition[] */
	private $transitions;

	/** @var StateMachineGraph|null */
	private $stateMachineGraph = null;

	/** @var DefinitionError[] */
	private $errors;

	/** @var DebugDataBag[] */
	private $debugData;

	/** @var string|null */
	private $referenceClass;

	/** @var string|null */
	private $repositoryClass;

	/** @var string|null */
	private $transitionsClass;


	/**
	 * StateMachineDefinition constructor.
	 *
	 * @internal
	 * @param string $machineType
	 * @param StateDefinition[] $states
	 * @param ActionDefinition[] $actions
	 * @param TransitionDefinition[] $transitions
	 * @param DefinitionError[] $errors
	 * @param DebugDataBag[] $debugData
	 */
	public function __construct(string $machineType, array $states, array $actions, array $transitions, array $errors,
		?string $referenceClass = null, ?string $transitionsClass = null, ?string $repositoryClass = null, array $debugData = [])
	{
		$this->machineType = $machineType;
		$this->states = $states;
		$this->actions = $actions;
		$this->transitions = $transitions;
		$this->errors = $errors;
		$this->referenceClass = $referenceClass;
		$this->transitionsClass = $transitionsClass;
		$this->repositoryClass = $repositoryClass;
		$this->debugData = $debugData;
	}


	/**
	 * @return string
	 */
	public function getMachineType(): string
	{
		return $this->machineType;
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
	 * @return ActionDefinition[]
	 */
	public function getActions(): array
	{
		return $this->actions;
	}

	public function getAction(string $name): ActionDefinition
	{
		if (isset($this->actions[$name])) {
			return $this->actions[$name];
		} else {
			throw new UndefinedActionException("Undefined action: $name");
		}
	}

	/**
	 * @return TransitionDefinition[]
	 */
	public function getTransitions(): array
	{
		return $this->transitions;
	}


	/**
	 * @param string|ActionDefinition $actionName
	 * @param string|StateDefinition $sourceStateName
	 * @return TransitionDefinition
	 */
	public function getTransition($action, $sourceState): TransitionDefinition
	{
		if (!($action instanceof ActionDefinition)) {
			$action = $this->getAction($action);
		}
		if (!($sourceState instanceof StateDefinition)) {
			$sourceState = $this->getState($sourceState);
		}
		return $action->getTransition($sourceState);
	}


	public function getGraph(): StateMachineGraph
	{
		return $this->stateMachineGraph ?? ($this->stateMachineGraph = new StateMachineGraph($this));
	}


	/**
	 * @return DebugDataBag[]
	 */
	public function getDebugData(): array
	{
		return $this->debugData;
	}


	public function hasErrors(): bool
	{
		return !empty($this->errors);
	}


	/**
	 * @return DefinitionError[]
	 */
	public function getErrors(): array
	{
		return $this->errors;
	}


	public function getReferenceClass(): ?string
	{
		return $this->referenceClass;
	}


	public function getTransitionsClass(): ?string
	{
		return $this->transitionsClass;
	}


	public function getRepositoryClass(): ?string
	{
		return $this->repositoryClass;
	}


	public function findReachableStates(StateDefinition $initialState = null): array
	{
		$g = $this->getGraph();
		$reachableStates = [];

		if ($initialState === null) {
			$initialState = $this->getState('');
		}

		$startNode = $g->getNodeByState($initialState, StateMachineNode::SOURCE);

		GraphSearch::DFS($g)
			->onNode(function (Node $node) use (& $reachableStates, $startNode) {
				if ($node instanceof StateMachineNode) {
					if ($node !== $startNode) {
						$state = $node->getState();
						$reachableStates[$state->getName()] = $state;
					}
					return true;
				} else {
					return false;
				}
			})
			->start([$startNode]);

		return $reachableStates;
	}

}
