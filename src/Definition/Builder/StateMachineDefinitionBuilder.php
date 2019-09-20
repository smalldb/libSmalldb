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
use Smalldb\StateMachine\Definition\DefinitionErrorInterface;
use Smalldb\StateMachine\Definition\PropertyDefinition;
use Smalldb\StateMachine\Definition\StateDefinition;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Definition\TransitionDefinition;
use Smalldb\StateMachine\Definition\UndefinedStateException;


class StateMachineDefinitionBuilder extends ExtensiblePlaceholder
{
	/** @var PreprocessorInterface[] */
	private $preprocessorQueue = [];

	/** @var StatePlaceholder[] */
	private $states = [];

	/** @var ActionPlaceholder[] */
	private $actions = [];

	/** @var TransitionPlaceholder[] */
	private $transitions = [];

	/** @var TransitionPlaceholder[][] */
	private $transitionsByState = [];

	/** @var PropertyPlaceholder[] */
	private $properties = [];

	/** @var string */
	private $machineType;

	/** @var DefinitionErrorInterface[] */
	private $errors = [];

	/** @var string|null */
	private $referenceClass = null;

	/** @var string|null */
	private $repositoryClass = null;

	/** @var string|null */
	private $transitionsClass = null;


	public function __construct()
	{
		parent::__construct([]);
	}


	public function addPreprocessor(PreprocessorInterface $preprocessor): void
	{
		$this->preprocessorQueue[] = $preprocessor;
	}


	public function runPreprocessors(): void
	{
		while (($preprocessor = array_shift($this->preprocessorQueue))) {
			$preprocessor->preprocessDefinition($this);
		}
	}


	public function build(): StateMachineDefinition
	{
		$this->runPreprocessors();

		if (empty($this->machineType)) {
			throw new StateMachineBuilderException("Machine type not set.");
		}

		// Check if we have the Not Exists state
		if (!isset($this->states[''])) {
			$this->addState('');
		}

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

		/** @var PropertyDefinition[] $properties */
		$properties = [];
		foreach ($this->properties as $propertyPlaceholder) {
			$property = $this->buildPropertyDefinition($propertyPlaceholder);
			$properties[$property->getName()] = $property;
		}

		return new StateMachineDefinition($this->machineType, $states, $actions, $transitions, $properties, $this->errors,
			$this->referenceClass, $this->transitionsClass, $this->repositoryClass, $this->buildExtensions());
	}

	protected function buildStateDefinition(StatePlaceholder $statePlaceholder): StateDefinition
	{
		return $statePlaceholder->buildStateDefinition();
	}


	protected function buildActionDefinition(ActionPlaceholder $actionPlaceholder, array $transitions): ActionDefinition
	{
		return $actionPlaceholder->buildActionDefinition($transitions);
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

		return $transitionPlaceholder->buildTransitionDefinition($sourceState, $targetStates);
	}


	protected function buildPropertyDefinition(PropertyPlaceholder $propertyPlaceholder): PropertyDefinition
	{
		return $propertyPlaceholder->buildPropertyDefinition();
	}


	/**
	 * Sort everything to make changes in generated definitions more stable when the source changes.
	 */
	public function sortPlaceholders()
	{
		ksort($this->states);
		ksort($this->actions);
		ksort($this->transitionsByState);
		foreach ($this->transitionsByState as & $t) {
			ksort($t);
		}

		// TODO: Sort properties too?
		//ksort($this->properties);
	}


	public function getState(string $name): StatePlaceholder
	{
		return $this->states[$name] ?? ($this->states[$name] = new StatePlaceholder($name));
	}


	public function addState(string $name): StatePlaceholder
	{
		if (isset($this->states[$name])) {
			throw new DuplicateStateException("State already exists: $name");
		} else {
			return ($this->states[$name] = new StatePlaceholder($name));
		}
	}


	public function getAction(string $name): ActionPlaceholder
	{
		return $this->actions[$name] ?? ($this->actions[$name] = new ActionPlaceholder($name));
	}


	public function addAction(string $name, ?string $color = null): ActionPlaceholder
	{
		if (isset($this->actions[$name])) {
			throw new DuplicateActionException("Action already exists: $name");
		} else {
			return ($this->actions[$name] = new ActionPlaceholder($name));
		}
	}


	public function getTransition(string $transitionName, string $sourceStateName): TransitionPlaceholder
	{
		if (isset($this->transitionsByState[$sourceStateName][$transitionName])) {
			return $this->transitionsByState[$sourceStateName][$transitionName];
		} else {
			return $this->addTransition($transitionName, $sourceStateName, []);
		}
	}


	public function addTransition(string $transitionName, string $sourceStateName, array $targetStateNames, ?string $color = null): TransitionPlaceholder
	{
		if (isset($this->transitionsByState[$sourceStateName][$transitionName])) {
			throw new DuplicateTransitionException("Transition \"$transitionName\" already exists in state \"$sourceStateName\".");
		} else {
			if (empty($this->actions[$transitionName])) {
				$this->addAction($transitionName);
			}

			$placeholder = new TransitionPlaceholder($transitionName, $sourceStateName, $targetStateNames, $color);
			$this->transitionsByState[$sourceStateName][$transitionName] = $placeholder;
			$this->transitions[] = $placeholder;
			return $placeholder;
		}
	}


	public function getProperty(string $name): PropertyPlaceholder
	{
		if (isset($this->properties[$name])) {
			return $this->properties[$name];
		} else {
			return $this->addProperty($name);
		}
	}


	public function addProperty(string $name, string $type = null, bool $isNullable = null): PropertyPlaceholder
	{
		if (isset($this->properties[$name])) {
			throw new DuplicatePropertyException("Property already exists: $name");
		} else {
			return ($this->properties[$name] = new PropertyPlaceholder($name, $type, $isNullable));
		}
	}


	public function getMachineType(): string
	{
		return $this->machineType;
	}


	public function setMachineType(string $machineType)
	{
		$this->machineType = $machineType;
	}


	public function addError(DefinitionErrorInterface $error): void
	{
		$this->errors[] = $error;
	}


	/**
	 * @return DefinitionErrorInterface[]
	 */
	public function getErrors(): array
	{
		return $this->errors;
	}


	public function hasErrors(): bool
	{
		return !empty($this->errors);
	}


	public function getReferenceClass(): ?string
	{
		return $this->referenceClass;
	}


	public function setReferenceClass(?string $referenceClass): void
	{
		$this->referenceClass = $referenceClass;
	}


	public function getRepositoryClass(): ?string
	{
		return $this->repositoryClass;
	}


	public function setRepositoryClass(?string $repositoryClass): void
	{
		$this->repositoryClass = $repositoryClass;
	}


	public function getTransitionsClass(): ?string
	{
		return $this->transitionsClass;
	}


	public function setTransitionsClass(?string $transitionsClass): void
	{
		$this->transitionsClass = $transitionsClass;
	}


}
