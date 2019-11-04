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

use Doctrine\DBAL\Connection;
use PDO;
use Smalldb\StateMachine\CodeGenerator\SmalldbClassGenerator;
use Smalldb\StateMachine\Provider\LambdaProvider;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\SmalldbDefinitionBagInterface;
use Smalldb\StateMachine\SmalldbDefinitionBagReader;
use Smalldb\StateMachine\Test\Database\ArrayDaoTables;
use Smalldb\StateMachine\Test\Database\SymfonyDemoDatabaseFactory;
use Smalldb\StateMachine\Test\Database\SymfonyDemoDatabase;
use Smalldb\StateMachine\Test\Example\Bpmn\PizzaDelivery;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItem;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItemRepository;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItemTransitions;
use Smalldb\StateMachine\Test\Example\Post\Post;
use Smalldb\StateMachine\Test\Example\Post\PostRepository;
use Smalldb\StateMachine\Test\Example\Post\PostTransitions;
use Smalldb\StateMachine\Test\Example\SupervisorProcess\SupervisorProcess;
use Smalldb\StateMachine\Test\Example\Tag\Tag;
use Smalldb\StateMachine\Test\Example\Tag\TagRepository;
use Smalldb\StateMachine\Test\Example\Tag\TagTransitions;
use Smalldb\StateMachine\Test\Example\User\User;
use Smalldb\StateMachine\Test\Example\User\UserRepository;
use Smalldb\StateMachine\Test\Example\User\UserTransitions;
use Smalldb\StateMachine\Test\TestTemplate\TestOutput;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;


class SymfonyDemoContainer extends AbstractSmalldbContainerFactory implements SmalldbFactory, CompilerPassInterface
{

	protected function createDefinitionReader(ContainerBuilder $c): SmalldbDefinitionBagReader
	{
		$bagReader = new SmalldbDefinitionBagReader();
		$bagReader->addFromAnnotatedClass(CrudItem::class);
		$bagReader->addFromAnnotatedClass(Post::class);
		$bagReader->addFromAnnotatedClass(Tag::class);
		$bagReader->addFromAnnotatedClass(User::class);
		$bagReader->addFromAnnotatedClass(SupervisorProcess::class);
		$bagReader->addFromAnnotatedClass(PizzaDelivery::class);
		return $bagReader;
	}


	protected function configureContainer(ContainerBuilder $c): ContainerBuilder
	{
		$c->autowire(Smalldb::class)
			->setPublic(true);

		// "Database"
		$c->autowire(ArrayDaoTables::class);

		// Symfony Demo Database
		$c->autowire(TestOutput::class);
		$c->autowire(SymfonyDemoDatabase::class)
			->setPublic(true);
		$c->setAlias(PDO::class, SymfonyDemoDatabase::class)
			->setPublic(true);

		// Symfony Demo Database using Doctrine DBAL
		$c->autowire(SymfonyDemoDatabaseFactory::class);
		$c->autowire(Connection::class)
			->setPublic(true)
			->setFactory([new Reference(SymfonyDemoDatabaseFactory::class), 'connect']);

		// Add Repositories
		$c->autowire(PostRepository::class)->setPublic(true);
		$c->autowire(TagRepository::class)->setPublic(true);
		$c->autowire(UserRepository::class)->setPublic(true);
		$c->autowire(CrudItemRepository::class)->setPublic(true);

		// Add transition implementations
		$c->autowire(PostTransitions::class)->setPublic(true);
		$c->autowire(TagTransitions::class)->setPublic(true);
		$c->autowire(UserTransitions::class)->setPublic(true);
		$c->autowire(CrudItemTransitions::class)->setPublic(true);

		$c->addCompilerPass($this);
		return $c;
	}


	public function process(ContainerBuilder $c)
	{
		$scg = new SmalldbClassGenerator('Smalldb\\GeneratedCode\\', $this->out->mkdir('generated'));

		$smalldb = $c->getDefinition(Smalldb::class);

		// Definition Bag
		$definitionBag = $this->createDefinitionReader($c)->getDefinitionBag();

		// Register & Autowire all state machine components
		foreach ($definitionBag->getAllDefinitions() as $machineType => $definition) {
			$referenceClass = $definition->getReferenceClass();
			$repositoryClass = $definition->getRepositoryClass();
			$transitionsClass = $definition->getTransitionsClass();

			$serviceReferences = [];

			if ($repositoryClass) {
				$serviceReferences[LambdaProvider::REPOSITORY] = new Reference($repositoryClass);
			}

			if ($transitionsClass) {
				$serviceReferences[LambdaProvider::TRANSITIONS_DECORATOR] = new Reference($transitionsClass);
			}

			$realReferenceClass = $referenceClass ? $scg->generateReferenceClass($referenceClass, $definition) : null;


			// Glue them together using a machine provider
			$providerId = "smalldb.$machineType.provider";
			$c->register($providerId, LambdaProvider::class)
				->addTag('container.service_locator')
				->addArgument($serviceReferences)
				->addArgument($machineType)
				->addArgument($realReferenceClass)
				->addArgument(new Reference(SmalldbDefinitionBagInterface::class));

			// Register state machine type
			$smalldb->addMethodCall('registerMachineType', [new Reference($providerId), [$referenceClass]]);
		}

		// Compile & register definition bag
		$bagName = 'GeneratedDefinitionBag_' . preg_replace('/.*\\\\/', '', get_class($this));
		$c->autowire(SmalldbDefinitionBagInterface::class, $scg->generateDefinitionBag($definitionBag, $bagName))
			->setPublic(true);
	}

}
