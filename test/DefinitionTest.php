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
namespace Smalldb\StateMachine\Test;

use PHPUnit\Framework\TestCase;
use Smalldb\StateMachine\Definition\ActionDefinition;
use Smalldb\StateMachine\Definition\StateDefinition;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Definition\StateMachineGraph\StateMachineEdge;
use Smalldb\StateMachine\Definition\StateMachineGraph\StateMachineGraph;
use Smalldb\StateMachine\Definition\StateMachineGraph\StateMachineNode;
use Smalldb\StateMachine\Definition\TransitionDefinition;
use Smalldb\StateMachine\Definition\UndefinedActionException;
use Smalldb\StateMachine\Definition\UndefinedStateException;
use Smalldb\StateMachine\Definition\UndefinedTransitionException;
use Smalldb\StateMachine\Graph\Grafovatko\GrafovatkoExporter;


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

		// Actions
		$aCreate = new ActionDefinition('create', [$tCreate->getSourceState()->getName() => $tCreate]);
		$aUpdate = new ActionDefinition('update', [$tUpdate->getSourceState()->getName() => $tUpdate]);
		$aDelete = new ActionDefinition('delete', [$tDelete->getSourceState()->getName() => $tDelete]);

		// State machine
		$stateMachineDefinition = new StateMachineDefinition('crud-item', time(),
			['' => $sNotExists, 'Exists' => $sExists],
			['create' => $aCreate, 'update' => $aUpdate, 'delete' => $aDelete],
			[$tCreate, $tUpdate, $tDelete], [], []);

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

		// Check actions
		$actions = $stateMachineDefinition->getActions();
		$this->assertCount(3, $actions);
		$this->assertContainsOnlyInstancesOf(ActionDefinition::class, $actions);

		// Check an action
		$aCreate = $stateMachineDefinition->getAction('create');
		$this->assertInstanceOf(ActionDefinition::class, $aCreate);
		$this->assertEquals('create', $aCreate->getName());
		$tCreate = $aCreate->getTransitions();
		$this->assertCount(1, $tCreate);
		$this->assertContainsOnlyInstancesOf(TransitionDefinition::class, $tCreate);

		// Check a transition
		$tUpdate = $stateMachineDefinition->getTransition('update', 'Exists');
		$this->assertInstanceOf(TransitionDefinition::class, $tUpdate);
		$this->assertEquals('update', $tUpdate->getName());
	}


	public function testDefinitionStateFail()
	{
		$stateMachineDefinition = $this->buildCrudStateMachine();
		$this->assertInstanceOf(StateMachineDefinition::class, $stateMachineDefinition);

		$this->expectException(UndefinedStateException::class);
		$stateMachineDefinition->getState('foo');
	}

	public function testDefinitionTransitionFail()
	{
		$stateMachineDefinition = $this->buildCrudStateMachine();
		$this->assertInstanceOf(StateMachineDefinition::class, $stateMachineDefinition);

		$this->expectException(UndefinedTransitionException::class);
		$stateMachineDefinition->getTransition('create', 'Exists');
	}

	public function testDefinitionActionFail()
	{
		$stateMachineDefinition = $this->buildCrudStateMachine();
		$this->assertInstanceOf(StateMachineDefinition::class, $stateMachineDefinition);

		$this->expectException(UndefinedActionException::class);
		$stateMachineDefinition->getAction('foo');
	}


	/*
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
	*/

	public function testGraph()
	{
		$stateMachine = $this->buildCrudStateMachine();
		$g = $stateMachine->getGraph();
		$this->assertInstanceOf(StateMachineGraph::class, $g);
		$this->assertEquals($stateMachine, $g->getStateMachine());

		$notExistsState = $stateMachine->getState('');
		$begin = $g->getNodeByState($notExistsState, StateMachineNode::SOURCE);
		$end = $g->getNodeByState($notExistsState, StateMachineNode::TARGET);
		$this->assertEquals($notExistsState, $begin->getState());
		$this->assertEquals($notExistsState, $end->getState());

		$this->assertInstanceOf(StateMachineNode::class, $begin);
		$this->assertInstanceOf(StateMachineNode::class, $end);
		$this->assertNotEquals($begin, $end);

		$this->assertTrue($begin->isSourceNode());
		$this->assertFalse($begin->isTargetNode());
		$this->assertFalse($end->isSourceNode());
		$this->assertTrue($end->isTargetNode());

		$existsNode = $g->getNodeByState($stateMachine->getState('Exists'), StateMachineNode::SOURCE);
		$this->assertInstanceOf(StateMachineNode::class, $existsNode);
		$this->assertTrue($existsNode->isSourceNode());
		$this->assertTrue($existsNode->isTargetNode());

		$this->assertContainsOnlyInstancesOf(StateMachineEdge::class,
			$g->getEdgesByTransition($stateMachine->getTransition('create', '')));
		$this->assertContainsOnlyInstancesOf(StateMachineEdge::class,
			$g->getEdgesByTransition($stateMachine->getTransition('update', 'Exists')));
		$this->assertContainsOnlyInstancesOf(StateMachineEdge::class,
			$g->getEdgesByTransition($stateMachine->getTransition('delete', 'Exists')));

		$tUpdate = $stateMachine->getTransition('update', 'Exists');
		$updateEdges = $g->getEdgesByTransition($tUpdate);
		$this->assertCount(1, $updateEdges);
		[$edgeUpdate] = $updateEdges;
		$this->assertEquals($tUpdate, $edgeUpdate->getTransition());

		$jsonObject = (new GrafovatkoExporter($g))->export();
		$this->assertIsArray($jsonObject);
	}

}
