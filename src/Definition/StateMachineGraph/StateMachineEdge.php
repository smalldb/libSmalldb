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

use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Definition\TransitionDefinition;
use Smalldb\Graph\Edge;
use Smalldb\Graph\NestedGraph;
use Smalldb\Graph\Node;
use Smalldb\StateMachine\LogicException;


class StateMachineEdge extends Edge
{
	private TransitionDefinition $transition;


	public function __construct(TransitionDefinition $transition, NestedGraph $graph, string $id, Node $start, Node $end, array $attrs)
	{
		parent::__construct($graph, $id, $start, $end, $attrs);
		$this->transition = $transition;
	}


	public function getTransition(): TransitionDefinition
	{
		return $this->transition;
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
