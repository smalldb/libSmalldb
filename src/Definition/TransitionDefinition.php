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

/**
 * Definition of a state machine transition.
 *
 * There may be multiple definitions of transitions with same names
 * but different source states.
 */
class TransitionDefinition
{
	/** @var string */
	private $name;

	/** @var StateDefinition */
	private $sourceState;

	/** @var StateDefinition[] */
	private $targetStates;


	/**
	 * TransitionDefinition constructor.
	 *
	 * @internal
	 */
	public function __construct(string $name, StateDefinition $sourceState, array $targetStates)
	{
		$this->name = $name;
		$this->sourceState = $sourceState;
		$this->targetStates = $targetStates;
	}

	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * Get source states of the transition.
	 */
	public function getSourceState(): StateDefinition
	{
		return $this->sourceState;
	}

	/**
	 * Get target states for the given source state.
	 *
	 * @return StateDefinition[]
	 */
	public function getTargetStates(): array
	{
		return $this->targetStates;
	}

}
