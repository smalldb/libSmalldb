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


/**
 * Class TransitionPlaceholder
 *
 * @internal
 */
class TransitionPlaceholder
{
	/** @var string */
	public $name;

	/** @var string */
	public $sourceState;

	/** @var string[] */
	public $targetStates;

	public function __construct(string $transitionName, string $sourceStateName, array $targetStateNames)
	{
		$this->name = $transitionName;
		$this->sourceState = $sourceStateName;
		$this->targetStates = $targetStateNames;
	}

}
