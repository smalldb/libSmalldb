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
use Psr\Container\ContainerInterface;
use Smalldb\StateMachine\AnnotationReader;
use Smalldb\StateMachine\ArrayMachine;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Provider\ContainerProvider;
use Smalldb\StateMachine\Provider\LambdaProvider;
use Smalldb\StateMachine\Provider\SmalldbProviderInterface;
use Smalldb\StateMachine\Reference;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItemMachine;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItemRef;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItemRepository;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItemTransitions;
use Smalldb\StateMachine\Test\Example\Database\ArrayDao;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;


class BasicMachineTest extends TestCase
{

	private function createCrudMachineSmalldb(): Smalldb
	{
		$smalldb = new Smalldb();

		// Definition
		$reader = new AnnotationReader(CrudItemMachine::class);
		$definition = $reader->getStateMachineDefinition();

		// Repository
		$dao = new ArrayDao();
		$repository = new CrudItemRepository($smalldb, $dao);

		// Transitions implementation
		$transitionsImplementation = new CrudItemTransitions($repository, $dao);

		// Glue them together using a machine provider
		$machineProvider = (new LambdaProvider())
			->setReferenceClass(CrudItemRef::class)
			->setDefinition($definition)
			->setTransitionsImplementation($transitionsImplementation)
			->setRepository($repository);

		// Register state machine type
		$smalldb->registerMachineType($definition->getMachineType(), $machineProvider);

		return $smalldb;
	}


	private function createCrudMachineContainer(): ContainerInterface
	{
		$c = new ContainerBuilder();

		$smalldb = $c->autowire(Smalldb::class)
			->setPublic(true);

		// Definition
		$reader = $c->register(AnnotationReader::class . ' $crudItemReader', AnnotationReader::class)
			->addArgument(CrudItemMachine::class);
		$definitionId = StateMachineDefinition::class . ' $crudItemDefinition';
		$c->register($definitionId, StateMachineDefinition::class)
			->setFactory([$reader, 'getStateMachineDefinition'])
			->setPublic(true);

		// Repository
		$crudItemDaoId = ArrayDao::class . ' $crudItemDao';
		$c->autowire($crudItemDaoId, ArrayDao::class);
		$c->autowire(CrudItemRepository::class)
			->setArgument(ArrayDao::class, new \Symfony\Component\DependencyInjection\Reference($crudItemDaoId))
			->setPublic(true);

		// Transitions implementation
		$transitionsId = CrudItemTransitions::class . ' $crudItemTransitionsImplementation';
		$c->autowire($transitionsId, CrudItemTransitions::class)
			->setArgument(ArrayDao::class, new \Symfony\Component\DependencyInjection\Reference($crudItemDaoId))
			->setPublic(true);

		// Glue them together using a machine provider
		$machineProvider = $c->autowire(ContainerProvider::class)
			->addMethodCall('setDefinitionId', [$definitionId])
			->addMethodCall('setTransitionsImplementationId', [$transitionsId])
			->addMethodCall('setRepositoryId', [CrudItemRepository::class])
			->addMethodCall('setReferenceClass', [CrudItemRef::class]);

		// Register state machine type
		$smalldb->addMethodCall('registerMachineType', ['crud-item', $machineProvider]);

		$c->compile();

		// Dump the container so that we can examine it.
		$dumper = new PhpDumper($c);
		$outputDir = __DIR__ . '/output';
		if (!is_dir($outputDir)) {
			mkdir($outputDir);
		}
		file_put_contents("$outputDir/BasicMachineContainer.php", $dumper->dump());

		return $c;
	}


	/**
	 * @dataProvider smalldbProvider
	 */
	public function testCrudMachine(callable $smalldbFactory)
	{
		/** @var Smalldb $smalldb */
		$smalldb = $smalldbFactory();
		$this->assertInstanceOf(Smalldb::class, $smalldb);

		$crudMachineProvider = $smalldb->getMachineProvider('crud-item');
		$this->assertInstanceOf(SmalldbProviderInterface::class, $crudMachineProvider);

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
		$this->assertNotEquals(null, $ref->getId());
		$this->assertEquals(CrudItemMachine::EXISTS, $ref->getState());

		// Usage: Delete
		$ref->delete();
		$this->assertEquals(CrudItemMachine::NOT_EXISTS, $ref->getState());

	}


	public function smalldbProvider()
	{
		yield "Static" => [function(): Smalldb { return $this->createCrudMachineSmalldb(); }];
		yield "Container" => [function(): Smalldb { return $this->createCrudMachineContainer()->get(Smalldb::class); }];
	}


	public function testMachine()
	{
		$this->markTestSkipped('Test obsolete.');

		// Initialize backend
		$smalldb = new \Smalldb\StateMachine\Smalldb();
		$smalldbBackend = new Smalldb\StateMachine\SimpleBackend();
		$smalldbBackend->initializeBackend([]);
		$smalldb->registerBackend($smalldbBackend);

		// Register machine type
		$article_json = json_decode(file_get_contents(dirname(__FILE__) . '/example/article.json'), TRUE);
		$smalldbBackend->registerMachineType('article', ArrayMachine::class, $article_json['state_machine']);

		// Get known machine types
		$knownTypes = [];
		foreach ($smalldb->getBackends() as $backend) {
			foreach ($backend->getKnownTypes() as $t) {
				$knownTypes[] = $t;
			}
		}
		$this->assertContains('article', $knownTypes);

		// Check the machine
		$machine = $smalldb->getMachine('article');
		$this->assertInstanceOf(ArrayMachine::class, $machine);

		// Get null ref
		$nullRef = $smalldb->nullRef('article');
		$this->assertInstanceOf(Reference::class, $nullRef);
		$this->assertEquals('', $nullRef->state);

		// Available actions for the null ref
		$availableActions = $nullRef->actions;
		$this->assertEquals(['create'], $availableActions);

		// Create machine instantion - invoke initial transition
		$ref = $nullRef->create();
		$this->assertEquals('writing', $ref->state);

		// Available actions for the new ref
		$this->assertEquals(['edit', 'submit'], $ref->actions);

	}

}
