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

use Smalldb\StateMachine\CodeGenerator\SmalldbClassGenerator;
use Smalldb\StateMachine\CodeGenerator\DefinitionBagGenerator;
use Smalldb\StateMachine\CodeGenerator\GeneratedClassLoader;
use Smalldb\StateMachine\CodeGenerator\ReferenceClassGenerator;
use Smalldb\StateMachine\Provider\LambdaProvider;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\SmalldbDefinitionBag;
use Smalldb\StateMachine\SmalldbDefinitionBagInterface;
use Smalldb\StateMachine\Test\Database\ArrayDaoTables;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItem;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItemRepository;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItemTransitions;
use Smalldb\StateMachine\Test\Example\Post\Post;
use Smalldb\StateMachine\Test\Example\Post\PostRepository;
use Smalldb\StateMachine\Test\Example\Post\PostTransitions;
use Smalldb\StateMachine\Test\Example\Tag\Tag;
use Smalldb\StateMachine\Test\Example\Tag\TagRepository;
use Smalldb\StateMachine\Test\Example\Tag\TagTransitions;
use Smalldb\StateMachine\Test\Example\User\User;
use Smalldb\StateMachine\Test\Example\User\UserRepository;
use Smalldb\StateMachine\Test\Example\User\UserTransitions;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;


class SymfonyDemoContainer extends AbstractSmalldbContainerFactory implements SmalldbFactory
{

	protected function configureContainer(ContainerBuilder $c): ContainerBuilder
	{
		$scg = new SmalldbClassGenerator('Smalldb\\GeneratedCode\\', $this->out->mkdir('generated'));
		$smalldb = $c->autowire(Smalldb::class)
			->setPublic(true);

		// Definition Bag
		$definitionBag = new SmalldbDefinitionBag();

		// "Database"
		$c->autowire(ArrayDaoTables::class);

		// State machines
		// TODO: Where to get this configuration? ... Definition bag builder?
		$machines = [
			'crud-item' => [CrudItem::class, CrudItemRepository::class, CrudItemTransitions::class],
			'post' => [Post::class, PostRepository::class, PostTransitions::class],
			'tag' => [Tag::class, TagRepository::class, TagTransitions::class],
			'user' => [User::class, UserRepository::class, UserTransitions::class],
		];

		// Register & Autowire all state machine components
		foreach ($machines as $machineType => [$refClass, $repositoryClass, $transitionsClass]) {
			$definition = $definitionBag->addFromAnnotatedClass($refClass);

			$c->autowire($repositoryClass)
				->setPublic(true);

			$c->autowire($transitionsClass);

			$realRefClass = $scg->generateReferenceClass($refClass, $definition);

			// Glue them together using a machine provider
			$providerId = "smalldb.$machineType.provider";
			$c->register($providerId, LambdaProvider::class)
				->addTag('container.service_locator')
				->addArgument([
					LambdaProvider::TRANSITIONS_DECORATOR => new Reference($transitionsClass),
					LambdaProvider::REPOSITORY => new Reference($repositoryClass),
				])
				->addArgument($machineType)
				->addArgument($realRefClass)
				->addArgument(new Reference(SmalldbDefinitionBagInterface::class));

			// Register state machine type
			$smalldb->addMethodCall('registerMachineType', [new Reference($providerId)]);
		}

		$c->autowire(SmalldbDefinitionBagInterface::class,
				$scg->generateDefinitionBag($definitionBag, 'GeneratedDefinitionBag_SymfonyDemoContainer'))
			->setPublic(true);
		return $c;
	}

}
