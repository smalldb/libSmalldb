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

use Smalldb\StateMachine\Definition\Builder\DuplicateActionException;
use Smalldb\StateMachine\Definition\Builder\DuplicatePropertyException;
use Smalldb\StateMachine\Definition\Builder\DuplicateStateException;
use Smalldb\StateMachine\Definition\Builder\DuplicateTransitionException;
use Smalldb\StateMachine\Definition\Builder\Preprocessor;
use Smalldb\StateMachine\Definition\Builder\PreprocessorList;
use Smalldb\StateMachine\Definition\Builder\PreprocessorPass;
use Smalldb\StateMachine\Definition\Builder\StateMachineBuilderException;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilder;
use Smalldb\StateMachine\Definition\Builder\StatePlaceholder;
use Smalldb\StateMachine\Definition\DefinitionError;
use Smalldb\StateMachine\Definition\PropertyDefinition;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\InvalidArgumentException;
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
		$builder = new StateMachineDefinitionBuilder(new PreprocessorList());
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

		$builder = new StateMachineDefinitionBuilder(new PreprocessorList());
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
		$builder = new StateMachineDefinitionBuilder(new PreprocessorList());
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
		$builder = new StateMachineDefinitionBuilder(new PreprocessorList());
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
		$builder = new StateMachineDefinitionBuilder(new PreprocessorList());
		$builder->addState('S');
		$this->expectException(StateMachineBuilderException::class);
		$builder->build();
	}


	public function testDuplicateState()
	{
		$builder = new StateMachineDefinitionBuilder(new PreprocessorList());
		$builder->setMachineType('foo');
		$builder->addState('S');
		$this->expectException(DuplicateStateException::class);
		$builder->addState('S');
	}


	public function testDuplicateAction()
	{
		$builder = new StateMachineDefinitionBuilder(new PreprocessorList());
		$builder->setMachineType('foo');
		$builder->addAction('a');
		$this->expectException(DuplicateActionException::class);
		$builder->addAction('a');
	}


	public function testDuplicateTransition()
	{
		$builder = new StateMachineDefinitionBuilder(new PreprocessorList());
		$builder->setMachineType('foo');
		$builder->addTransition('a', 'A', ['B']);
		$this->expectException(DuplicateTransitionException::class);
		$builder->addTransition('a', 'A', ['C']);
	}


	public function testGetState()
	{
		$builder = new StateMachineDefinitionBuilder(new PreprocessorList());
		$builder->setMachineType('foo');

		$a1 = $builder->getState('A');  // State created
		$a2 = $builder->getState('A');
		$this->assertSame($a1, $a2);
	}


	public function testGetAction()
	{
		$builder = new StateMachineDefinitionBuilder(new PreprocessorList());
		$builder->setMachineType('foo');
		$a1 = $builder->getAction('a');
		$a2 = $builder->getAction('a');
		$this->assertSame($a1, $a2);
	}


	public function testGetTransition()
	{
		$builder = new StateMachineDefinitionBuilder(new PreprocessorList());
		$builder->setMachineType('foo');
		$t1 = $builder->getTransition('a', 'A');
		$t2 = $builder->getTransition('a', 'A');
		$this->assertSame($t1, $t2);
	}


	public function testProperty()
	{
		$builder = new StateMachineDefinitionBuilder(new PreprocessorList());
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

		$fooProperty = $definition->getProperty('foo');
		$this->assertSame($properties['foo'], $fooProperty);

		$barProperty = $definition->getProperty('bar');
		$this->assertSame($properties['bar'], $barProperty);
	}


	public function testDuplicateProperty()
	{
		$builder = new StateMachineDefinitionBuilder(new PreprocessorList());
		$builder->setMachineType('foo');
		$builder->addProperty('a');
		$this->expectException(DuplicatePropertyException::class);
		$builder->addProperty('a');
	}


	public function testMissingProperty()
	{
		$builder = new StateMachineDefinitionBuilder(new PreprocessorList());
		$builder->setMachineType('foo');
		$builder->addProperty('a');
		$newProperty = $builder->getProperty('b'); // not 'a'
		$this->assertEquals('b', $newProperty->name);
	}


	public function testGetProperty()
	{
		$builder = new StateMachineDefinitionBuilder(new PreprocessorList());
		$builder->setMachineType('foo');
		$p1 = $builder->getProperty('a');
		$p2 = $builder->getProperty('a');
		$this->assertSame($p1, $p2);
	}


	public function testMissingSourceState()
	{
		$builder = new StateMachineDefinitionBuilder(new PreprocessorList());
		$builder->setMachineType('foo');
		$builder->addTransition('a', 'A', ['B']);
		$this->expectException(StateMachineBuilderException::class); // A is missing
		$builder->build();
	}


	public function testMissingTargetState()
	{
		$builder = new StateMachineDefinitionBuilder(new PreprocessorList());
		$builder->setMachineType('foo');
		$builder->addTransition('a', 'A', ['B', 'C']);
		$builder->addState('A');
		$builder->addState('B');
		$this->expectException(StateMachineBuilderException::class); // C is missing
		$builder->build();
	}


	public function testExtraNode()
	{
		$builder = new StateMachineDefinitionBuilder(new PreprocessorList());
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


	public function testPreprocessor()
	{
		$preprocessorList = new PreprocessorList();
		$builder = new StateMachineDefinitionBuilder($preprocessorList);

		$preprocessorPass = $this->createMock(PreprocessorPass::class);

		$preprocessor = $this->createMock(Preprocessor::class);
		$preprocessor->expects($this->once())->method('supports')->with($preprocessorPass)->willReturn(true);
		$preprocessor->expects($this->once())->method('preprocessDefinition')->with($builder);
		$preprocessorList->addPreprocessor($preprocessor);

		$builder->setMachineType('Foo');
		$builder->addState('A');
		$builder->addState('B');
		$builder->addTransition('t', 'A', ['B']);
		$builder->addPreprocessorPass($preprocessorPass);
		$builder->build();
	}


	public function testNoNameAddState()
	{
		$builder = new StateMachineDefinitionBuilder(new PreprocessorList());
		// Empty state name is valid; no exception should be thrown.
		$s = $builder->addState('');
		$this->assertInstanceOf(StatePlaceholder::class, $s);
	}


	public function testNoNameGetState()
	{
		$builder = new StateMachineDefinitionBuilder(new PreprocessorList());
		// Empty state name is valid; no exception should be thrown.
		$s = $builder->getState('');
		$this->assertInstanceOf(StatePlaceholder::class, $s);
	}


	public function testNoNameGetAction()
	{
		$builder = new StateMachineDefinitionBuilder(new PreprocessorList());
		$this->expectException(InvalidArgumentException::class);
		$builder->getAction('');
	}


	public function testNoNameAddAction()
	{
		$builder = new StateMachineDefinitionBuilder(new PreprocessorList());
		$this->expectException(InvalidArgumentException::class);
		$builder->addAction('');
	}


	public function testNoNameGetTransition()
	{
		$builder = new StateMachineDefinitionBuilder(new PreprocessorList());
		$this->expectException(InvalidArgumentException::class);
		$builder->getTransition('', '');
	}


	public function testNoNameAddTransition()
	{
		$builder = new StateMachineDefinitionBuilder(new PreprocessorList());
		$this->expectException(InvalidArgumentException::class);
		$builder->addTransition('', '', []);
	}


	public function testNoNameGetProperty()
	{
		$builder = new StateMachineDefinitionBuilder(new PreprocessorList());
		$this->expectException(InvalidArgumentException::class);
		$builder->getProperty('');
	}


	public function testNoNameAddProperty()
	{
		$builder = new StateMachineDefinitionBuilder(new PreprocessorList());
		$this->expectException(InvalidArgumentException::class);
		$builder->addProperty('');
	}


	public function testMTime()
	{
		$builder = new StateMachineDefinitionBuilder(new PreprocessorList());
		$this->assertNull($builder->getMTime());

		$t = time();

		$builder->setMTime($t - 2000);
		$this->assertEquals($t - 2000, $builder->getMTime());

		$builder->addMTime($t - 1000);
		$this->assertEquals($t - 1000, $builder->getMTime());

		$builder->addMTime($t - 1500);
		$this->assertEquals($t - 1000, $builder->getMTime());

		$builder->setMTime(null);
		$this->assertNull($builder->getMTime());

		$builder->addMTime($t - 1500);
		$this->assertEquals($t - 1500, $builder->getMTime());
	}

}
