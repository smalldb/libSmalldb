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
use Smalldb\StateMachine\SmalldbDefinitionBag;
use Smalldb\StateMachine\SmalldbDefinitionBagInterface;
use Smalldb\StateMachine\Test\Database\ArrayDaoTables;
use Smalldb\StateMachine\Test\Database\SymfonyDemoDatabaseFactory;
use Smalldb\StateMachine\Test\Database\SymfonyDemoDatabase;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItem;
use Smalldb\StateMachine\Test\Example\Post\Post;
use Smalldb\StateMachine\Test\Example\SupervisorProcess\SupervisorProcess;
use Smalldb\StateMachine\Test\Example\Tag\Tag;
use Smalldb\StateMachine\Test\Example\User\User;
use Smalldb\StateMachine\Test\TestTemplate\TestOutput;
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
		$definitionBag->addFromAnnotatedClass(CrudItem::class);
		$definitionBag->addFromAnnotatedClass(Post::class);
		$definitionBag->addFromAnnotatedClass(Tag::class);
		$definitionBag->addFromAnnotatedClass(User::class);
		$definitionBag->addFromAnnotatedClass(SupervisorProcess::class);

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

		// Register & Autowire all state machine components
		foreach ($definitionBag->getAllDefinitions() as $machineType => $definition) {
			$referenceClass = $definition->getReferenceClass();
			$repositoryClass = $definition->getRepositoryClass();
			$transitionsClass = $definition->getTransitionsClass();

			$serviceReferences = [];

			if ($repositoryClass) {
				$serviceReferences[LambdaProvider::REPOSITORY] = new Reference($repositoryClass);
				$c->autowire($repositoryClass)
					->setPublic(true);
			}

			if ($transitionsClass) {
				$serviceReferences[LambdaProvider::TRANSITIONS_DECORATOR] = new Reference($transitionsClass);
				$c->autowire($transitionsClass);
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
		$c->autowire(SmalldbDefinitionBagInterface::class,
				$scg->generateDefinitionBag($definitionBag, 'GeneratedDefinitionBag_SymfonyDemoContainer'))
			->setPublic(true);
		return $c;
	}

}
