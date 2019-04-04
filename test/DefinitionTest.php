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

use PHPUnit\Framework\TestCase;
use Smalldb\StateMachine\Definition\StateDefinition;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Definition\StateMachineGraph\StateMachineEdge;
use Smalldb\StateMachine\Definition\StateMachineGraph\StateMachineGraph;
use Smalldb\StateMachine\Definition\StateMachineGraph\StateMachineNode;
use Smalldb\StateMachine\Definition\TransitionDefinition;


class DefinitionTest extends TestCase
{
	private function buildCrudStateMachine(): StateMachineDefinition
	{
		// States
		$sNotExists = new StateDefinition('');
		$sExists = new StateDefinition('Exists');

		// Transitions
		$tCreate = new TransitionDefinition('create', $sNotExists, ['Exists' => $sExists]);
		$tUpdate = new TransitionDefinition('update', $sExists, ['Exists' => $sExists]);
		$tDelete = new TransitionDefinition('delete', $sExists, ['' => $sNotExists]);

		// State machine
		$stateMachineDefinition = new StateMachineDefinition(
			['' => $sNotExists, 'Exists' => $sExists],
			['create' => $tCreate, 'update' => $tUpdate, 'delete' => $tDelete]);

		return $stateMachineDefinition;
	}


	public function testRawCrudDefinition()
	{
		$stateMachineDefinition = $this->buildCrudStateMachine();
		$this->assertInstanceOf(StateMachineDefinition::class, $stateMachineDefinition);

		// Check a state
		$sExists = $stateMachineDefinition->getState('Exists');
		$this->assertInstanceOf(StateDefinition::class, $sExists);
		$this->assertEquals('Exists', $sExists->getName());

		// Check a transition
		$tUpdate = $stateMachineDefinition->getTransition('Exists', 'update');
		$this->assertInstanceOf(TransitionDefinition::class, $tUpdate);
		$this->assertEquals('update', $tUpdate->getName());
	}


	public function testRawCrudDefinitionPerformance()
	{
		$iterationCount = 1e3;
		$t1 = microtime(true);

		for ($i = 0; $i < $iterationCount; $i++) {
			$this->buildCrudStateMachine();
		}

		$t2 = microtime(true);
		$t_us = ($t2 - $t1) / $iterationCount * 1e6;

		if ($t_us > 50) {
			$this->markTestIncomplete("CRUD definition is too slow; it took $t_us µs to build.");
		}
		$this->assertTrue(true);
	}

	public function testGraph()
	{
		$stateMachine = $this->buildCrudStateMachine();
		$g = $stateMachine->getGraph();
		$this->assertInstanceOf(StateMachineGraph::class, $g);

		$begin = $g->getNodeByState($stateMachine->getState(''), $g::NODE_SOURCE);
		$end = $g->getNodeByState($stateMachine->getState(''), $g::NODE_TARGET);

		$this->assertInstanceOf(StateMachineNode::class, $begin);
		$this->assertInstanceOf(StateMachineNode::class, $end);
		$this->assertNotEquals($begin, $end);

		$this->assertInstanceOf(StateMachineNode::class,
			$g->getNodeByState($stateMachine->getState('Exists'), $g::NODE_SOURCE));

		$this->assertContainsOnlyInstancesOf(StateMachineEdge::class,
			$g->getEdgesByTransition($stateMachine->getTransition('', 'create')));
		$this->assertContainsOnlyInstancesOf(StateMachineEdge::class,
			$g->getEdgesByTransition($stateMachine->getTransition('Exists', 'update')));
		$this->assertContainsOnlyInstancesOf(StateMachineEdge::class,
			$g->getEdgesByTransition($stateMachine->getTransition('Exists', 'delete')));

		$export = new \Smalldb\StateMachine\Graph\GraphExportGrafovatko($g);
		$jsonObject = $export->export();
		$this->assertIsArray($jsonObject);
	}

}
