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
use Smalldb\StateMachine\ReferenceDataSource\NotExistsException;
use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItem;
use Smalldb\StateMachine\Test\Example\Post\Post;
use Smalldb\StateMachine\Test\Example\Post\PostData;
use Smalldb\StateMachine\Test\Example\Post\PostDataImmutable;
use Smalldb\StateMachine\Test\SmalldbFactory\BrokenCrudItemBasic;
use Smalldb\StateMachine\Test\SmalldbFactory\CrudItemBasic;
use Smalldb\StateMachine\Test\SmalldbFactory\CrudItemContainer;
use Smalldb\StateMachine\Test\SmalldbFactory\CrudItemDefinitionBag;
use Smalldb\StateMachine\Test\SmalldbFactory\CrudItemServiceLocator;
use Smalldb\StateMachine\Test\SmalldbFactory\SmalldbFactory;
use Smalldb\StateMachine\Test\SmalldbFactory\SymfonyDemoContainer;
use Smalldb\StateMachine\Test\SmalldbFactory\YamlDemoContainer;
use Smalldb\StateMachine\Transition\MissingTransitionImplementationException;
use Smalldb\StateMachine\Transition\TransitionAssertException;


class BasicMachineTest extends TestCase
{

	/**
	 * @dataProvider smalldbProvider
	 */
	public function testCrudMachine(string $smalldbFactoryClass, string $machineType, $testData)
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
		$ref->create($testData);
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


	/**
	 * @dataProvider smalldbProvider
	 */
	public function testRegisteredMachines(string $smalldbFactoryClass, string $machineType)
	{
		/** @var SmalldbFactory $smalldbFactory */
		$smalldbFactory = new $smalldbFactoryClass();
		$smalldb = $smalldbFactory->createSmalldb();
		$this->assertInstanceOf(Smalldb::class, $smalldb);

		$provider = $smalldb->getMachineProvider($machineType);
		$this->assertInstanceOf(SmalldbProviderInterface::class, $provider);
		$this->assertEquals($machineType, $provider->getMachineType());

		$this->assertEquals($provider->getReferenceClass(), $smalldb->getReferenceClass($machineType));
		$this->assertEquals($provider->getRepository(), $smalldb->getRepository($machineType));
		$this->assertEquals($provider->getDefinition(), $smalldb->getDefinition($machineType));
		$this->assertEquals($provider->getTransitionsDecorator(), $smalldb->getTransitionsDecorator($machineType));
	}


	private function createPostData(): PostDataImmutable
	{
		$dataPost = new PostData();
		$dataPost->setId(1);
		$dataPost->setTitle('Foo');
		$dataPost->setSlug('foo');
		$dataPost->setSummary('Foo foo.');
		$dataPost->setContent('Foo foo foo foo foo.');
		$dataPost->setPublishedAt(new \DateTimeImmutable());
		$dataPost->setAuthorId(1);
		return new PostDataImmutable($dataPost);
	}


	public function smalldbProvider()
	{
		$dataCrudItem = ['name' => 'Foo'];

		yield "CRUD Item Basic" => [CrudItemBasic::class, 'crud-item', $dataCrudItem];
		yield "CRUD Item Container" => [CrudItemContainer::class, 'crud-item', $dataCrudItem];
		yield "CRUD Item Service Locator" => [CrudItemServiceLocator::class, 'crud-item', $dataCrudItem];
		yield "CRUD Item Definition Bag" => [CrudItemDefinitionBag::class, 'crud-item', $dataCrudItem];
		yield "Symfony Demo Container" => [SymfonyDemoContainer::class, 'crud-item', $dataCrudItem];
		yield "Symfony Demo Container - Post" => [SymfonyDemoContainer::class, 'post', $this->createPostData()];
		yield "YAML Container" => [YamlDemoContainer::class, 'crud-item', $dataCrudItem];
	}


	/**
	 * @return CrudItem
	 */
	private function createBrokenCrudItemRef(): CrudItem
	{
		/** @var SmalldbFactory $smalldbFactory */
		$smalldbFactory = new BrokenCrudItemBasic();
		$smalldb = $smalldbFactory->createSmalldb();

		// Create a null reference
		/** @var CrudItem $ref */
		$ref = $smalldb->nullRef('crud-item');
		$this->assertInstanceOf(ReferenceInterface::class, $ref);
		$this->assertEquals(null, $ref->getId());
		$this->assertEquals(CrudItem::NOT_EXISTS, $ref->getState());

		// Create the item
		$ref->create(['name' => 'Foo']);
		$id = $ref->getId();
		$state = $ref->getState();
		$this->assertNotEquals(null, $id);
		$this->assertEquals(CrudItem::EXISTS, $state);

		return $ref;
	}


	public function testBrokenCrudMachineTransition()
	{
		$ref = $this->createBrokenCrudItemRef();

		// Broken update of the item
		$this->expectException(TransitionAssertException::class);
		$ref->update(['name' => 'Bar']);
	}


	public function testMissingCrudMachineTransition()
	{
		$ref = $this->createBrokenCrudItemRef();

		// Try to delete the item, but the transition is not implemented.
		$this->expectException(MissingTransitionImplementationException::class);
		$ref->delete();
	}


	public function testLoadDataInNotExistsState()
	{
		/** @var SmalldbFactory $smalldbFactory */
		$smalldbFactory = new SymfonyDemoContainer();
		$smalldb = $smalldbFactory->createSmalldb();

		// Create a null reference
		/** @var Post $ref */
		$ref = $smalldb->nullRef(Post::class);
		$this->assertInstanceOf(ReferenceInterface::class, $ref);
		$this->assertEquals(null, $ref->getId());
		$this->assertEquals(CrudItem::NOT_EXISTS, $ref->getState());

		$this->expectException(NotExistsException::class);
		$ref->getTitle();
	}

}
