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
class StateMachineDefinition extends ExtensibleDefinition
{
	/** @var string */
	private $machineType;

	/** @var int */
	private $mtime;

	/** @var StateDefinition[] */
	private $states;

	/** @var ActionDefinition[] */
	private $actions;

	/** @var TransitionDefinition[] */
	private $transitions;

	/** @var StateMachineGraph|null */
	private $stateMachineGraph = null;

	/** @var PropertyDefinition[] */
	private $properties;

	/** @var DefinitionErrorInterface[] */
	private $errors;

	/** @var string|null */
	private $referenceClass;

	/** @var string|null */
	private $repositoryClass;

	/** @var string|null */
	private $transitionsClass;


	/**
	 * StateMachineDefinition constructor.
	 *
	 * @param string $machineType
	 * @param int $mtime
	 * @param StateDefinition[] $states
	 * @param ActionDefinition[] $actions
	 * @param TransitionDefinition[] $transitions
	 * @param array $properties
	 * @param DefinitionErrorInterface[] $errors
	 * @param string|null $referenceClass
	 * @param string|null $transitionsClass
	 * @param string|null $repositoryClass
	 * @param ExtensionInterface[] $extensions
	 * @internal
	 */
	public function __construct(string $machineType, int $mtime,
		array $states, array $actions, array $transitions, array $properties, array $errors,
		?string $referenceClass = null, ?string $transitionsClass = null, ?string $repositoryClass = null,
		array $extensions = [])
	{
		parent::__construct($extensions);
		$this->machineType = $machineType;
		$this->mtime = $mtime;
		$this->states = $states;
		$this->actions = $actions;
		$this->transitions = $transitions;
		$this->properties = $properties;
		$this->errors = $errors;
		$this->referenceClass = $referenceClass;
		$this->transitionsClass = $transitionsClass;
		$this->repositoryClass = $repositoryClass;
	}


	/**
	 * @return string
	 */
	public function getMachineType(): string
	{
		return $this->machineType;
	}


	public function getMTime(): int
	{
		return $this->mtime;
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
	 * @param string|ActionDefinition $action
	 * @param string|StateDefinition $sourceState
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


	/**
	 * @return PropertyDefinition[]
	 */
	public function getProperties(): array
	{
		return $this->properties;
	}


	public function getGraph(): StateMachineGraph
	{
		return $this->stateMachineGraph ?? ($this->stateMachineGraph = new StateMachineGraph($this));
	}


	public function hasErrors(): bool
	{
		return !empty($this->errors);
	}


	/**
	 * @return DefinitionErrorInterface[]
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


	public function jsonSerialize()
	{
		return array_merge(get_object_vars($this), parent::jsonSerialize());
	}

}
