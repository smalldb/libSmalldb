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
use Smalldb\StateMachine\Definition\Builder\DuplicateActionException;
use Smalldb\StateMachine\Definition\Builder\DuplicatePropertyException;
use Smalldb\StateMachine\Definition\Builder\DuplicateStateException;
use Smalldb\StateMachine\Definition\Builder\DuplicateTransitionException;
use Smalldb\StateMachine\Definition\Builder\StateMachineBuilderException;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilder;
use Smalldb\StateMachine\Definition\DefinitionError;
use Smalldb\StateMachine\Definition\PropertyDefinition;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Definition\UndefinedStateException;
use Smalldb\StateMachine\Test\Example\Post\Post;
use Smalldb\StateMachine\Test\Example\Post\PostRepository;
use Smalldb\StateMachine\Test\Example\Post\PostTransitions;


class DefinitionBuilderTest extends TestCase
{

	/**
	 * Assert that all states in $definition are reachable.
	 */
	private function assertAllStatesReachable(StateMachineDefinition $definition, int $expectedStateCount = -1)
	{
		$reachableStates = $definition->findReachableStates();
		$allStates = $definition->getStates();
		if ($expectedStateCount == -1) {
			$expectedStateCount = count($allStates);
		}
		$this->assertCount($expectedStateCount, $allStates);
		$this->assertCount($expectedStateCount, $reachableStates);
		$this->assertEquals($allStates, $reachableStates);
	}


	public function testCrudBuilder()
	{
		$builder = new StateMachineDefinitionBuilder();
		$builder->setMachineType('crud-item');

		$builder->addTransition('create', '', ['Exists']);
		$builder->addTransition('update', 'Exists', ['Exists']);
		$builder->addTransition('delete', 'Exists', ['']);

		$builder->addState('Exists');

		$definition = $builder->build();
		$this->assertInstanceOf(StateMachineDefinition::class, $definition);
		$this->assertAllStatesReachable($definition, 2);
	}


	public function testDiceBuilder()
	{
		$D = 6;

		$builder = new StateMachineDefinitionBuilder();
		$builder->setMachineType('dice');
		$builder->addState('Ready');
		$builder->addTransition('create', '', ['Ready']);
		$builder->addTransition('delete', 'Ready', ['']);

		$diceStates = [];
		for ($d = 1; $d <= $D; $d++) {
			$diceStates[] = $diceState = 'Dice' . $d;
			$builder->addState($diceState);
			$builder->addTransition('next', $diceState, ['Ready']);
		}
		$builder->addTransition('roll', 'Ready', $diceStates);

		$definition = $builder->build();
		$this->assertInstanceOf(StateMachineDefinition::class, $definition);
		$this->assertAllStatesReachable($definition, $D + 2);
	}


	public function testDefinitionClasses()
	{
		$builder = new StateMachineDefinitionBuilder();
		$builder->setMachineType('Foo');
		$this->assertEquals('Foo', $builder->getMachineType());

		$builder->setReferenceClass(Post::class);
		$this->assertEquals(Post::class, $builder->getReferenceClass());

		$builder->setTransitionsClass(PostTransitions::class);
		$this->assertEquals(PostTransitions::class, $builder->getTransitionsClass());

		$builder->setRepositoryClass(PostRepository::class);
		$this->assertEquals(PostRepository::class, $builder->getRepositoryClass());
	}


	public function testErrors()
	{
		$builder = new StateMachineDefinitionBuilder();
		$builder->setMachineType('Foo');

		$this->assertFalse($builder->hasErrors());

		$errMessage = "Foo is broken.";
		$error = new DefinitionError($errMessage);
		$builder->addError($error);

		$this->assertTrue($builder->hasErrors());
		$this->assertEquals([$error], $builder->getErrors());
		$this->assertEquals($errMessage, $error->getMessage());

		$definition = $builder->build();
		$builtErrors = $definition->getErrors();
		$this->assertEquals([$error], $builtErrors);
	}


	public function testMissingMachineType()
	{
		$builder = new StateMachineDefinitionBuilder();
		$builder->addState('S');
		$this->expectException(StateMachineBuilderException::class);
		$builder->build();
	}

	public function testDuplicateState()
	{
		$builder = new StateMachineDefinitionBuilder();
		$builder->setMachineType('foo');
		$builder->addState('S');
		$this->expectException(DuplicateStateException::class);
		$builder->addState('S');
	}

	public function testDuplicateAction()
	{
		$builder = new StateMachineDefinitionBuilder();
		$builder->setMachineType('foo');
		$builder->addAction('a');
		$this->expectException(DuplicateActionException::class);
		$builder->addAction('a');
	}

	public function testDuplicateTransition()
	{
		$builder = new StateMachineDefinitionBuilder();
		$builder->setMachineType('foo');
		$builder->addTransition('a', 'A', ['B']);
		$this->expectException(DuplicateTransitionException::class);
		$builder->addTransition('a', 'A', ['C']);
	}

	public function testProperty()
	{
		$builder = new StateMachineDefinitionBuilder();
		$builder->setMachineType('Foo');
		$foo = $builder->addProperty("foo", "string", true);
		$bar = $builder->addProperty("bar", "int", false);

		$this->assertEquals('string', $foo->type);
		$this->assertTrue($foo->isNullable);

		$this->assertEquals('int', $bar->type);
		$this->assertFalse($bar->isNullable);
		$definition = $builder->build();

		$properties = $definition->getProperties();

		$this->assertInstanceOf(PropertyDefinition::class, $properties['foo']);
		$this->assertEquals('foo', $properties['foo']->getName());
		$this->assertEquals('string', $properties['foo']->getType());
		$this->assertTrue($properties['foo']->isNullable());

		$this->assertInstanceOf(PropertyDefinition::class, $properties['bar']);
		$this->assertEquals('bar', $properties['bar']->getName());
		$this->assertEquals('int', $properties['bar']->getType());
		$this->assertFalse($properties['bar']->isNullable());
	}

	public function testDuplicateProperty()
	{
		$builder = new StateMachineDefinitionBuilder();
		$builder->setMachineType('foo');
		$builder->addProperty('a');
		$this->expectException(DuplicatePropertyException::class);
		$builder->addProperty('a');
	}

	public function testMissingSourceState()
	{
		$builder = new StateMachineDefinitionBuilder();
		$builder->setMachineType('foo');
		$builder->addTransition('a', 'A', ['B']);
		$this->expectException(UndefinedStateException::class); // A is missing
		$builder->build();
	}

	public function testMissingTargetState()
	{
		$builder = new StateMachineDefinitionBuilder();
		$builder->setMachineType('foo');
		$builder->addTransition('a', 'A', ['B', 'C']);
		$builder->addState('A');
		$builder->addState('B');
		$this->expectException(UndefinedStateException::class); // C is missing
		$builder->build();
	}

	public function testExtraNode()
	{
		$builder = new StateMachineDefinitionBuilder();
		$builder->setMachineType('foo');
		$builder->addTransition('a', '', ['A']);
		$builder->addTransition('a', 'A', ['']);
		$builder->addTransition('b', 'A', ['B']);
		$builder->addState('A');
		$builder->addState('B');
		$definition = $builder->build();

		$graph = $definition->getGraph();
		$extraNode = $graph->createNode('x');
		$graph->createEdge(null, $graph->getNodeByState($definition->getState('A')), $extraNode);
		$reachableStates = $definition->findReachableStates();
		// The findReachableStates() should quietly ignore the $extraNode.

		$this->assertEquals($definition->getStates(), $reachableStates);
	}


}
