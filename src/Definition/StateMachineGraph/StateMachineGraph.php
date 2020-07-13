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
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Definition\TransitionDefinition;
use Smalldb\Graph\Graph;
use Smalldb\StateMachine\InvalidArgumentException;

class StateMachineGraph extends Graph
{
	/** @var StateMachineEdge[][][] */
	private array $transitionToEdgesMap = [];

	/** @var StateMachineNode[][] */
	private array $stateToNodesMap = [];

	private StateMachineDefinition $stateMachine;


	public function __construct(StateMachineDefinition $stateMachine)
	{
		parent::__construct();

		$this->stateMachine = $stateMachine;

		foreach ($stateMachine->getStates() as $state) {
			$this->createStateMachineNodes($state);
		}

		foreach ($stateMachine->getTransitions() as $transition) {
			$this->createStateMachineEdges($transition);
		}
	}


	private function mapStateNameToGraphNodeId(StateDefinition $state, int $sourceOrTarget)
	{
		$name = $state->getName();

		if ($name === '') {
			return ($sourceOrTarget & StateMachineNode::SOURCE) ? 'n:begin' : 'n:end';
		} else {
			return 's:' . $name;
		}
	}


	/**
	 * @return StateMachineNode[]
	 */
	protected function createStateMachineNodes(StateDefinition $state): array
	{
		$stateName = $state->getName();
		if ($stateName === '') {
			$sId = $this->mapStateNameToGraphNodeId($state, StateMachineNode::SOURCE);
			$tId = $this->mapStateNameToGraphNodeId($state, StateMachineNode::TARGET);
			$s = new StateMachineNode($state, StateMachineNode::SOURCE, $this, $sId, ['state' => $state]);
			$t = new StateMachineNode($state, StateMachineNode::TARGET, $this, $tId, ['state' => $state]);
		} else {
			$id = $this->mapStateNameToGraphNodeId($state, StateMachineNode::SOURCE_AND_TARGET);
			$s = $t = new StateMachineNode($state, StateMachineNode::SOURCE_AND_TARGET, $this, $id, ['state' => $state]);
		}

		return ($this->stateToNodesMap[$stateName] = [
			StateMachineNode::SOURCE => $s,
			StateMachineNode::TARGET => $t,
		]);
	}


	/**
	 * @return StateMachineEdge[]
	 */
	protected function createStateMachineEdges(TransitionDefinition $transition): array
	{
		$edges = [];
		$sourceStateName = $transition->getSourceState()->getName();
		$transitionName = $transition->getName();

		foreach ($transition->getTargetStates() as $targetState) {
			$sourceNode = $this->getNodeByState($transition->getSourceState(), StateMachineNode::SOURCE);
			$targetNode = $this->getNodeByState($targetState, StateMachineNode::TARGET);
			$edgeId = 't:' . $sourceStateName . ':' . $transitionName . ':' . $targetState->getName();
			$edges[] = new StateMachineEdge($transition, $this, $edgeId, $sourceNode, $targetNode, ['transition' => $transition]);
		}

		$this->transitionToEdgesMap[$sourceStateName][$transitionName] = $edges;
		return $edges;
	}

	/**
	 * @return StateMachineDefinition
	 */
	public function getStateMachine(): StateMachineDefinition
	{
		return $this->stateMachine;
	}

	/**
	 * @return StateMachineEdge[]
	 */
	public function getEdgesByTransition(TransitionDefinition $transition): array
	{
		$sourceStateName = $transition->getSourceState()->getName();
		$transitionName = $transition->getName();
		if (!isset($this->transitionToEdgesMap[$sourceStateName][$transitionName])) {
			throw new InvalidArgumentException("The transition has no edges assigned."); // @codeCoverageIgnore
		} else {
			return $this->transitionToEdgesMap[$sourceStateName][$transitionName];
		}
	}

	/**
	 * @return StateMachineNode
	 */
	public function getNodeByState(StateDefinition $state, int $sourceOrTarget = StateMachineNode::SOURCE): StateMachineNode
	{
		$stateName = $state->getName();
		if (!isset($this->stateToNodesMap[$stateName][$sourceOrTarget])) {
			throw new InvalidArgumentException("The state has no nodes assigned."); // @codeCoverageIgnore
		} else {
			return $this->stateToNodesMap[$stateName][$sourceOrTarget];
		}
	}

}
