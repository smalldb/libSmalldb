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

use Smalldb\StateMachine\ClassGenerator\ReferenceClassGenerator;
use Smalldb\StateMachine\ClassGenerator\SmalldbClassGenerator;
use Smalldb\StateMachine\Provider\LambdaProvider;
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
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;


class CrudItemDefinitionBag extends AbstractSmalldbContainerFactory implements SmalldbFactory
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
			$scg->generateDefinitionBag($definitionReader->getDefinitionBag(), 'GeneratedDefinitionBag_CrudItemDefinitionBag'));

		// Repository
		$c->autowire(ArrayDaoTables::class);
		$c->autowire(CrudItemRepository::class);

		// Transitions implementation
		$c->autowire(TransitionGuard::class, AllowingTransitionGuard::class);
		$c->autowire(CrudItemTransitions::class);

		$realRefClass = $scg->generateReferenceClass(CrudItem::class, $definition);

		// Glue them together using a machine provider
		$machineProvider = $c->register(LambdaProvider::class)
			->setFactory(LambdaProvider::class . '::createWithDefinitionBag')
			->setArguments([
				'crud-item',
				$realRefClass,
				new Reference(SmalldbDefinitionBagInterface::class),
				new ServiceClosureArgument(new Reference(CrudItemTransitions::class)),
				new ServiceClosureArgument(new Reference(CrudItemRepository::class)),
			]);

		// Register state machine type
		$smalldb->addMethodCall('registerMachineType', [$machineProvider, [CrudItem::class]]);

		return $c;
	}

}
