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

use Smalldb\StateMachine\AnnotationReader;
use Smalldb\StateMachine\CodeGenerator\ReferenceClassGenerator;
use Smalldb\StateMachine\CodeGenerator\SmalldbClassGenerator;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Provider\LambdaProvider;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\SmalldbDefinitionBag;
use Smalldb\StateMachine\SmalldbDefinitionBagInterface;
use Smalldb\StateMachine\Test\Database\ArrayDaoTables;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItem;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItemRepository;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItemTransitions;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;


class CrudItemServiceLocator extends AbstractSmalldbContainerFactory implements SmalldbFactory
{

	protected function configureContainer(ContainerBuilder $c): ContainerBuilder
	{
		$scg = new SmalldbClassGenerator('Smalldb\\GeneratedCode\\', $this->out->mkdir('generated'));
		$smalldb = $c->autowire(Smalldb::class)
			->setPublic(true);

		// Definition Bag
		$definitionBag = new SmalldbDefinitionBag();
		$definition = $definitionBag->addFromAnnotatedClass(CrudItem::class);
		$c->autowire(SmalldbDefinitionBagInterface::class,
			$scg->generateDefinitionBag($definitionBag, 'GeneratedDefinitionBag_CrudItemServiceLocator'));

		// Definition
		$definitionId = StateMachineDefinition::class . ' $crudItemDefinition';
		$c->register($definitionId, StateMachineDefinition::class)
			->setFactory([new Reference(SmalldbDefinitionBagInterface::class), 'getDefinition'])
			->addArgument('crud-item')
			->setPublic(true);

		// Repository
		$c->autowire(ArrayDaoTables::class);
		$c->autowire(CrudItemRepository::class);

		// Transitions implementation
		$transitionsId = CrudItemTransitions::class . ' $crudItemTransitionsImplementation';
		$c->autowire($transitionsId, CrudItemTransitions::class);

		$realRefClass = $scg->generateReferenceClass(CrudItem::class, $definition);

		// Glue them together using a machine provider
		$machineProvider = $c->register(LambdaProvider::class)
			->addTag('container.service_locator')
			->addArgument([
				LambdaProvider::DEFINITION => new Reference($definitionId),
				LambdaProvider::TRANSITIONS_DECORATOR => new Reference($transitionsId),
				LambdaProvider::REPOSITORY => new Reference(CrudItemRepository::class),
			])
			->addArgument('crud-item')
			->addArgument($realRefClass)
			->addArgument(null);

		// Register state machine type
		$smalldb->addMethodCall('registerMachineType', [$machineProvider, [CrudItem::class]]);

		return $c;
	}

}
