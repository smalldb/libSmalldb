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

namespace Smalldb\StateMachine\Definition\StateMachineGraph;

use Smalldb\StateMachine\Definition\StateDefinition;
use Smalldb\Graph\NestedGraph;
use Smalldb\Graph\Node;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\LogicException;


class StateMachineNode extends Node
{
	public const SOURCE = 1;
	public const TARGET = 2;
	public const SOURCE_AND_TARGET = self::SOURCE | self::TARGET;

	private StateDefinition $state;
	private int $sourceOrTarget;


	public function __construct(StateDefinition $state, int $sourceOrTarget, NestedGraph $graph, string $id, array $attrs)
	{
		parent::__construct($graph, $id, $attrs);
		$this->state = $state;
		$this->sourceOrTarget = $sourceOrTarget;
	}


	public function getState(): StateDefinition
	{
		return $this->state;
	}


	public function isSourceNode(): bool
	{
		return ($this->sourceOrTarget & self::SOURCE) === self::SOURCE;
	}


	public function isTargetNode(): bool
	{
		return ($this->sourceOrTarget & self::TARGET) === self::TARGET;
	}


	public function getStateMachine(): StateMachineDefinition
	{
		$rootGraph = $this->getRootGraph();
		if ($rootGraph instanceof StateMachineGraph) {
			return $rootGraph->getStateMachine();
		} else {
			throw new LogicException("The root graph is not a " . StateMachineGraph::class);
		}
	}

}
