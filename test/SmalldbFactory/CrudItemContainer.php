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

use Smalldb\StateMachine\Definition\AnnotationReader\AnnotationReader;
use Smalldb\StateMachine\ClassGenerator\ReferenceClassGenerator;
use Smalldb\StateMachine\ClassGenerator\SmalldbClassGenerator;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Provider\ContainerProvider;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\SmalldbDefinitionBag;
use Smalldb\StateMachine\SmalldbDefinitionBagInterface;
use Smalldb\StateMachine\SmalldbDefinitionBagReader;
use Smalldb\StateMachine\Test\Database\ArrayDaoTables;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItem;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItemRepository;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItemTransitions;
use Smalldb\StateMachine\Transition\AllowingTransitionGuard;
use Smalldb\StateMachine\Transition\TransitionGuard;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\String\Slugger\SluggerInterface;


class CrudItemContainer extends AbstractSmalldbContainerFactory implements SmalldbFactory
{

	protected function configureContainer(ContainerBuilder $c): ContainerBuilder
	{
		$scg = new SmalldbClassGenerator('Smalldb\\GeneratedCode\\', $this->out->mkdir('generated'));
		$smalldb = $c->autowire(Smalldb::class)
			->setPublic(true);

		// Definition Bag
		$definitionReader = new SmalldbDefinitionBagReader();
		$definition = $definitionReader->addFromAnnotatedClass(CrudItem::class);
		$c->autowire(SmalldbDefinitionBagInterface::class,
			$scg->generateDefinitionBag($definitionReader->getDefinitionBag(), 'GeneratedDefinitionBag_CrudItemContainer'));

		// Definition
		$definitionId = StateMachineDefinition::class . ' $crudItemDefinition';
		$c->register($definitionId, StateMachineDefinition::class)
			->setFactory([new Reference(SmalldbDefinitionBagInterface::class), 'getDefinition'])
			->addArgument('crud-item')
			->setPublic(true);

		// Repository
		$c->autowire(ArrayDaoTables::class);
		$c->autowire(CrudItemRepository::class)
			->setPublic(true);

		// Transitions implementation
		$transitionsId = CrudItemTransitions::class . ' $crudItemTransitionsImplementation';
		$c->autowire(TransitionGuard::class, AllowingTransitionGuard::class);
		$c->autowire($transitionsId, CrudItemTransitions::class)
			->setPublic(true);

		$realRefClass = $scg->generateReferenceClass(CrudItem::class, $definition);

		// Glue them together using a machine provider
		$machineProvider = $c->autowire(ContainerProvider::class)
			->addMethodCall('setDefinitionId', [$definitionId])
			->addMethodCall('setTransitionsImplementationId', [$transitionsId])
			->addMethodCall('setRepositoryId', [CrudItemRepository::class])
			->addMethodCall('setReferenceClass', [$realRefClass]);

		// Register state machine type
		$smalldb->addMethodCall('registerMachineType', [$machineProvider, [CrudItem::class]]);

		// Additional services
		$c->setAlias(SluggerInterface::class, AsciiSlugger::class);
		$c->autowire(AsciiSlugger::class);

		return $c;
	}

}
