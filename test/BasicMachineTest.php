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
use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItem;
use Smalldb\StateMachine\Test\SmalldbFactory\CrudItemBasic;
use Smalldb\StateMachine\Test\SmalldbFactory\CrudItemContainer;
use Smalldb\StateMachine\Test\SmalldbFactory\CrudItemDefinitionBag;
use Smalldb\StateMachine\Test\SmalldbFactory\CrudItemServiceLocator;
use Smalldb\StateMachine\Test\SmalldbFactory\SmalldbFactory;
use Smalldb\StateMachine\Test\SmalldbFactory\SymfonyDemoContainer;
use Smalldb\StateMachine\Test\SmalldbFactory\YamlDemoContainer;


class BasicMachineTest extends TestCase
{

	/**
	 * @dataProvider smalldbProvider
	 */
	public function testCrudMachine(string $smalldbFactoryClass, string $machineType)
	{
		/** @var SmalldbFactory $smalldbFactory */
		$smalldbFactory = new $smalldbFactoryClass();
		$smalldb = $smalldbFactory->createSmalldb();
		$this->assertInstanceOf(Smalldb::class, $smalldb);

		// Check the provider
		$crudMachineProvider = $smalldb->getMachineProvider($machineType);
		$this->assertInstanceOf(SmalldbProviderInterface::class, $crudMachineProvider);

		// Check the definition
		$definition = $crudMachineProvider->getDefinition();
		$this->assertEquals($machineType, $definition->getMachineType());
		$this->assertCount(2, $definition->findReachableStates());
		$this->assertCount(3, $definition->getActions());

		// Try to create a null reference
		/** @var CrudItem $ref */
		$ref = $smalldb->nullRef($machineType);
		$this->assertInstanceOf(ReferenceInterface::class, $ref);
		$this->assertEquals(null, $ref->getId());
		$this->assertEquals(CrudItem::NOT_EXISTS, $ref->getState());

		// Usage: Create
		$ref->create(['name' => 'Foo']);
		$id = $ref->getId();
		$state = $ref->getState();
		$this->assertNotEquals(null, $id);
		$this->assertEquals(CrudItem::EXISTS, $state);

		// Try another reference
		$ref2 = $smalldb->ref($machineType, $id);
		$state2 = $ref2->getState();
		$this->assertEquals($state, $state2);

		// Usage: Delete
		$ref->delete();
		$this->assertEquals(CrudItem::NOT_EXISTS, $ref->getState());
	}


	public function smalldbProvider()
	{
		yield "CRUD Item Basic" => [CrudItemBasic::class, 'crud-item'];
		yield "CRUD Item Container" => [CrudItemContainer::class, 'crud-item'];
		yield "CRUD Item Service Locator" => [CrudItemServiceLocator::class, 'crud-item'];
		yield "CRUD Item Definition Bag" => [CrudItemDefinitionBag::class, 'crud-item'];
		yield "Symfony Demo Container" => [SymfonyDemoContainer::class, 'crud-item'];
		//yield "Symfony Demo Container - Post" => [SymfonyDemoContainer::class, 'post'];
		yield "YAML Container" => [YamlDemoContainer::class, 'crud-item'];
	}

}
