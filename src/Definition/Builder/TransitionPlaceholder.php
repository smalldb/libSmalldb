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

use Smalldb\StateMachine\Definition\StateDefinition;
use Smalldb\StateMachine\Definition\TransitionDefinition;


/**
 * Class TransitionPlaceholder
 *
 * @internal
 */
class TransitionPlaceholder extends ExtensiblePlaceholder
{
	public string $name;
	public string $sourceState;

	/** @var string[] */
	public array $targetStates;


	public function __construct(string $transitionName, string $sourceStateName, array $targetStateNames, array $extensionPlaceholders = [])
	{
		parent::__construct($extensionPlaceholders);
		$this->name = $transitionName;
		$this->sourceState = $sourceStateName;
		$this->targetStates = $targetStateNames;
	}


	public function buildTransitionDefinition(StateDefinition $sourceState, array $targetStates): TransitionDefinition
	{
		return new TransitionDefinition($this->name, $sourceState, $targetStates, $this->buildExtensions());
	}

}
