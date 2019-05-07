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
use Smalldb\StateMachine\Provider\SmalldbProviderInterface;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItemMachine;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItemRef;
use Smalldb\StateMachine\Test\SmalldbFactory\CrudItemBasic;
use Smalldb\StateMachine\Test\SmalldbFactory\CrudItemContainer;
use Smalldb\StateMachine\Test\SmalldbFactory\CrudItemDefinitionBag;
use Smalldb\StateMachine\Test\SmalldbFactory\CrudItemServiceLocator;
use Smalldb\StateMachine\Test\SmalldbFactory\SmalldbFactory;


class BasicMachineTest extends TestCase
{

	/**
	 * @dataProvider smalldbProvider
	 */
	public function testCrudMachine(string $smalldbFactoryClass)
	{
		/** @var SmalldbFactory $smalldbFactory */
		$smalldbFactory = new $smalldbFactoryClass();
		$smalldb = $smalldbFactory->createSmalldb();
		$this->assertInstanceOf(Smalldb::class, $smalldb);

		// Check the provider
		$crudMachineProvider = $smalldb->getMachineProvider('crud-item');
		$this->assertInstanceOf(SmalldbProviderInterface::class, $crudMachineProvider);
		$this->assertEquals(CrudItemRef::class, $crudMachineProvider->getReferenceClass());

		// Check the definition
		$definition = $crudMachineProvider->getDefinition();
		$this->assertEquals('crud-item', $definition->getMachineType());
		$this->assertCount(2, $definition->findReachableStates());
		$this->assertCount(3, $definition->getActions());

		// Try to create a null reference
		/** @var CrudItemRef $ref */
		$ref = $smalldb->nullRef('crud-item');
		$this->assertInstanceOf(CrudItemRef::class, $ref);
		$this->assertInstanceOf(CrudItemMachine::class, $ref);
		$this->assertEquals(null, $ref->getId());
		$this->assertEquals(CrudItemMachine::NOT_EXISTS, $ref->getState());

		// Usage: Create
		$ref->create('Foo');
		$id = $ref->getId();
		$state = $ref->getState();
		$this->assertNotEquals(null, $id);
		$this->assertEquals(CrudItemMachine::EXISTS, $state);

		// Try another reference
		$ref2 = $smalldb->ref('crud-item', $id);
		$state2 = $ref2->getState();
		$this->assertEquals($state, $state2);

		// Usage: Delete
		$ref->delete();
		$this->assertEquals(CrudItemMachine::NOT_EXISTS, $ref->getState());
	}


	public function smalldbProvider()
	{
		yield "Basic" => [CrudItemBasic::class];
		yield "Container" => [CrudItemContainer::class];
		yield "Service Locator" => [CrudItemServiceLocator::class];
		yield "Definition Bag" => [CrudItemDefinitionBag::class];
	}

}
