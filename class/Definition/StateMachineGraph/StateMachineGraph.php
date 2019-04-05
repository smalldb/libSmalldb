<?php
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
use Smalldb\StateMachine\Graph\Graph;
use Smalldb\StateMachine\InvalidArgumentException;

class StateMachineGraph extends Graph
{
	public const NODE_SOURCE = 0;
	public const NODE_TARGET = 1;

	/** @var StateMachineEdge[][][] */
	private $transitionToEdgesMap = [];

	/** @var StateMachineNode[][] */
	private $stateToNodesMap = [];

	/** @var StateMachineDefinition  */
	private $stateMachine;


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

		return $this;
	}


	private function mapStateNameToGraphNodeId(StateDefinition $state, int $sourceOrTarget)
	{
		$name = $state->getName();

		if ($name === '') {
			return $sourceOrTarget == self::NODE_SOURCE ? 'n:begin' : 'n:end';
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
			$sId = $this->mapStateNameToGraphNodeId($state, self::NODE_SOURCE);
			$tId = $this->mapStateNameToGraphNodeId($state, self::NODE_TARGET);
			$s = new StateMachineNode($state, $this, $sId, ['state' => $state]);
			$t = new StateMachineNode($state, $this, $tId, ['state' => $state]);
		} else {
			$id = $this->mapStateNameToGraphNodeId($state, true);
			$s = $t = new StateMachineNode($state, $this, $id, ['state' => $state]);
		}

		return ($this->stateToNodesMap[$stateName] = [
			self::NODE_SOURCE => $s,
			self::NODE_TARGET => $t,
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
			$sourceNode = $this->getNodeByState($transition->getSourceState(), self::NODE_SOURCE);
			$targetNode = $this->getNodeByState($targetState, self::NODE_TARGET);
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
	public function getNodeByState(StateDefinition $state, int $sourceOrTarget = self::NODE_SOURCE): StateMachineNode
	{
		$stateName = $state->getName();
		if (!isset($this->stateToNodesMap[$stateName][$sourceOrTarget])) {
			throw new InvalidArgumentException("The state has no nodes assigned."); // @codeCoverageIgnore
		} else {
			return $this->stateToNodesMap[$stateName][$sourceOrTarget];
		}
	}

}
