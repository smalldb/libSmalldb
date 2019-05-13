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
namespace Smalldb\StateMachine\Test\SmalldbFactory;

use Psr\Container\ContainerInterface;
use Smalldb\StateMachine\AnnotationReader;
use Smalldb\StateMachine\CodeGenerator\ReferenceClassGenerator;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Provider\ContainerProvider;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\SmalldbDefinitionBag;
use Smalldb\StateMachine\Test\Database\ArrayDaoTables;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItem;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItemRef;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItemRepository;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItemTransitions;
use Smalldb\StateMachine\Test\TestTemplate\TestOutput;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;


class CrudItemContainer implements SmalldbFactory
{

	/**
	 * Initialize Smalldb with a crud-item state machine.
	 */
	public function createSmalldb(): Smalldb
	{
		return $this->createCrudMachineContainer()->get(Smalldb::class);
	}


	private function createCrudMachineContainer(): ContainerInterface
	{
		$out = new TestOutput();
		$referencesDir = $out->mkdir('references');

		$c = new ContainerBuilder();

		$smalldb = $c->autowire(Smalldb::class)
			->setPublic(true);

		// Definition
		$reader = $c->register(AnnotationReader::class . ' $crudItemReader', AnnotationReader::class)
			->addArgument(CrudItem::class);
		$definitionId = StateMachineDefinition::class . ' $crudItemDefinition';
		$c->register($definitionId, StateMachineDefinition::class)
			->setFactory([$reader, 'getStateMachineDefinition'])
			->setPublic(true);

		// FIXME: Remove duplicate definition bag
		$definitionBag = new SmalldbDefinitionBag();
		$definition = $definitionBag->addFromAnnotatedClass(CrudItem::class);

		// Reference Generator
		$refGenerator = new ReferenceClassGenerator($referencesDir);

		// Repository
		$c->autowire(ArrayDaoTables::class);
		$c->autowire(CrudItemRepository::class)
			->setPublic(true);

		// Transitions implementation
		$transitionsId = CrudItemTransitions::class . ' $crudItemTransitionsImplementation';
		$c->autowire($transitionsId, CrudItemTransitions::class)
			->setPublic(true);

		$realRefClass = $refGenerator->generateReferenceClass(CrudItem::class, $definition);

		// Glue them together using a machine provider
		$machineProvider = $c->autowire(ContainerProvider::class)
			->addMethodCall('setDefinitionId', [$definitionId])
			->addMethodCall('setTransitionsImplementationId', [$transitionsId])
			->addMethodCall('setRepositoryId', [CrudItemRepository::class])
			->addMethodCall('setReferenceClass', [$realRefClass]);

		// Register state machine type
		$smalldb->addMethodCall('registerMachineType', [$machineProvider]);

		$c->compile();

		// Dump the container so that we can examine it.
		$dumper = new PhpDumper($c);
		$output = new TestOutput();
		$output->writeResource(basename(__FILE__), $dumper->dump());

		return $c;
	}

}
