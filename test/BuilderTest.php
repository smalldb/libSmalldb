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
namespace Smalldb\StateMachine\Test;

use PHPUnit\Framework\TestCase;
use Smalldb\StateMachine\Definition\Builder\DuplicateActionException;
use Smalldb\StateMachine\Definition\Builder\DuplicateStateException;
use Smalldb\StateMachine\Definition\Builder\DuplicateTransitionException;
use Smalldb\StateMachine\Definition\Builder\StateMachineBuilderException;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilder;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Definition\UndefinedStateException;


class BuilderTest extends TestCase
{

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
	}


	public function testDiceBuilder()
	{
		$D = 6;

		$builder = new StateMachineDefinitionBuilder();
		$builder->setMachineType('dice');
		$builder->addState('Ready');
		$builder->addTransition('create', '', ['Ready']);

		$diceStates = [];
		for ($d = 1; $d <= $D; $d++) {
			$diceStates[] = $diceState = 'Dice' . $d;
			$builder->addState($diceState);
			$builder->addTransition('next', $diceState, ['Ready']);
		}
		$builder->addTransition('roll', 'Ready', $diceStates);

		$definition = $builder->build();
		$this->assertInstanceOf(StateMachineDefinition::class, $definition);
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

}
