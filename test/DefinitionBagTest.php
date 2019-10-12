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
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\InvalidArgumentException;
use Smalldb\StateMachine\SmalldbDefinitionBag;
use Smalldb\StateMachine\Test\Example\Bpmn\PizzaDelivery;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItem;
use Smalldb\StateMachine\Test\Example\Post\Post;
use Smalldb\StateMachine\Test\Example\SupervisorProcess\SupervisorProcess;
use Smalldb\StateMachine\Utils\ClassLocator\ComposerClassLocator;
use Smalldb\StateMachine\Utils\ClassLocator\Psr4ClassLocator;


class DefinitionBagTest extends TestCase
{

	public function testDefinitionBag()
	{
		$bag = new SmalldbDefinitionBag();
		$this->assertEmpty($bag->getAllDefinitions(), "A new definition bag should be empty.");

		$fooDefinition = new StateMachineDefinition('foo', time(), [], [], [], [], []);
		$barDefinition = new StateMachineDefinition('bar', time(), [], [], [], [], []);
		$bag->addDefinition($fooDefinition);
		$bag->addDefinition($barDefinition);

		$crudItemDefinition = $bag->addFromAnnotatedClass(CrudItem::class);

		$allDefinitions = $bag->getAllDefinitions();
		$this->assertContains($fooDefinition, $allDefinitions);
		$this->assertContains($barDefinition, $allDefinitions);
		$this->assertContains($crudItemDefinition, $allDefinitions);

		$allMachineTypes = $bag->getAllMachineTypes();
		$this->assertContains('foo', $allMachineTypes);
		$this->assertContains('bar', $allMachineTypes);
		$this->assertContains($crudItemDefinition->getMachineType(), $allMachineTypes);

		$retrievedFooDef = $bag->getDefinition('foo');
		$this->assertEquals($fooDefinition, $retrievedFooDef);
	}


	public function testUndefinedDefinition()
	{
		$bag = new SmalldbDefinitionBag();
		$bag->addDefinition(new StateMachineDefinition('foo1', time(), [], [], [], [], []));
		$bag->addDefinition(new StateMachineDefinition('foo2', time(), [], [], [], [], []));
		$this->assertNotEmpty($bag->getAllDefinitions(), "The definition bag should not be empty.");

		$this->expectException(InvalidArgumentException::class);
		$bag->getDefinition('bar');
	}


	public function testDuplicateDefinition()
	{
		$bag = new SmalldbDefinitionBag();
		$bag->addDefinition(new StateMachineDefinition('foo', time(), [], [], [], [], []));

		$this->expectException(InvalidArgumentException::class);
		$bag->addDefinition(new StateMachineDefinition('foo', time(), [], [], [], [], []));
	}


	public function testDuplicateDefinitionFromAnnotatedClass()
	{
		$bag = new SmalldbDefinitionBag();
		$bag->addFromAnnotatedClass(CrudItem::class);

		$this->expectException(InvalidArgumentException::class);
		$bag->addFromAnnotatedClass(CrudItem::class);
	}


	public function testAliases()
	{
		$bag = new SmalldbDefinitionBag();
		$this->assertEmpty($bag->getAllDefinitions(), "A new definition bag should be empty.");

		$fooDefinition = new StateMachineDefinition('foo', time(), [], [], [], [], []);
		$barDefinition = new StateMachineDefinition('bar', time(), [], [], [], [], []);
		$bag->addDefinition($fooDefinition);
		$bag->addDefinition($barDefinition);
		$this->assertNotEmpty($bag->getAllDefinitions(), "The definition bag should not be empty.");

		$bag->addAlias('F', 'foo');

		$aliasedDefinition = $bag->getDefinition('F');
		$this->assertEquals($fooDefinition, $aliasedDefinition);

		$aliases = $bag->getAllAliases();
		$this->assertEquals(['F' => 'foo'], $aliases);

	}


	public function testDuplicateAlias1()
	{
		$bag = new SmalldbDefinitionBag();
		$fooDefinition = new StateMachineDefinition('foo', time(), [], [], [], [], []);
		$bag->addDefinition($fooDefinition);

		$bag->addAlias('F', 'foo');

		$this->expectException(InvalidArgumentException::class);
		$bag->addAlias('F', 'foo');
	}


	public function testDuplicateAlias2()
	{
		$bag = new SmalldbDefinitionBag();
		$fooDefinition = new StateMachineDefinition('foo', time(), [], [], [], [], []);
		$bag->addDefinition($fooDefinition);

		$this->expectException(InvalidArgumentException::class);
		$bag->addAlias('foo', 'bar');
	}


	public function testInvalidAlias()
	{
		$bag = new SmalldbDefinitionBag();
		$fooDefinition = new StateMachineDefinition('foo', time(), [], [], [], [], []);
		$bag->addDefinition($fooDefinition);

		$this->expectException(InvalidArgumentException::class);
		$bag->addAlias('F', 'bar');
	}


	public function testAddFromPsr4Directory()
	{
		$classLocator = new Psr4ClassLocator('Smalldb\StateMachine\Test\Example', __DIR__ . '/Example', []);

		$bag = new SmalldbDefinitionBag();
		$foundDefs = $bag->addFromClassLocator($classLocator);
		$this->assertNotEmpty($foundDefs);
		$this->assertInstanceOf(StateMachineDefinition::class, $bag->getDefinition(CrudItem::class));
		$this->assertInstanceOf(StateMachineDefinition::class, $bag->getDefinition('crud-item'));
		$this->assertInstanceOf(StateMachineDefinition::class, $bag->getDefinition(Post::class));
		$this->assertInstanceOf(StateMachineDefinition::class, $bag->getDefinition(PizzaDelivery::class));
		$this->assertInstanceOf(StateMachineDefinition::class, $bag->getDefinition(SupervisorProcess::class));
	}


	public function testAddFromComposer()
	{
		$classLocator = new ComposerClassLocator(dirname(__DIR__), [], ['test/output', 'test/BadExample']);

		$bag = new SmalldbDefinitionBag();
		$foundDefs = $bag->addFromClassLocator($classLocator);
		$this->assertNotEmpty($foundDefs);
		$this->assertInstanceOf(StateMachineDefinition::class, $bag->getDefinition(CrudItem::class));
		$this->assertInstanceOf(StateMachineDefinition::class, $bag->getDefinition('crud-item'));
		$this->assertInstanceOf(StateMachineDefinition::class, $bag->getDefinition(Post::class));
		$this->assertInstanceOf(StateMachineDefinition::class, $bag->getDefinition(PizzaDelivery::class));
		$this->assertInstanceOf(StateMachineDefinition::class, $bag->getDefinition(SupervisorProcess::class));
	}


	public function testAddFromPsr4DirectoryNoDir()
	{
		$bag = new SmalldbDefinitionBag();
		$this->expectException(InvalidArgumentException::class);
		$bag->addFromClassLocator(new Psr4ClassLocator('Smalldb\StateMachine\Test\Example', __DIR__ . '/Nonexistent-Directory', []));
	}

}
