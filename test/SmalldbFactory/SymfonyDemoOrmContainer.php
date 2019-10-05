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

use Doctrine\Bundle\DoctrineBundle\ManagerConfigurator;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ContainerRepositoryFactory;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\Tools\Setup;
use Psr\Container\ContainerInterface;
use Smalldb\StateMachine\SmalldbDefinitionBag;
use Smalldb\StateMachine\Test\SymfonyDemo\Repository\PostRepository;
use Smalldb\StateMachine\Test\SymfonyDemo\Repository\TagRepository;
use Smalldb\StateMachine\Test\SymfonyDemo\Repository\UserRepository;
use Smalldb\StateMachine\Test\SymfonyDemo\SmalldbRepository\SmalldbPostRepository;
use Smalldb\StateMachine\Test\SymfonyDemo\StateMachine\PostRef;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;


/**
 * SymfonyDemoContainer with Doctrine ORM.
 */
class SymfonyDemoOrmContainer extends SymfonyDemoContainer
{

	protected function createDefinitionBag(): SmalldbDefinitionBag
	{
		$definitionBag = new SmalldbDefinitionBag();
		$definitionBag->addFromAnnotatedClass(PostRef::class);
		return $definitionBag;
	}

	protected function configureContainer(ContainerBuilder $c): ContainerBuilder
	{
		$c = parent::configureContainer($c);

		$c->autowire(EntityManager::class)
			->setFactory([EntityManager::class, 'create'])
			->addArgument(new Reference(Connection::class))
			->addArgument(new Reference(Configuration::class))
			->setPublic(true);

		$c->autowire(Configuration::class)
			->setFactory([__CLASS__, 'createAnnotationMetadataConfiguration'])
			->addArgument(new Reference(ContainerInterface::class))
			->setPublic(true);

		$c->autowire(Registry::class)
			->addArgument(new Reference(ContainerInterface::class))
			->addArgument([Connection::class => Connection::class])
			->addArgument([EntityManager::class => EntityManager::class])
			->addArgument(Connection::class)
			->addArgument(EntityManager::class)
			->setPublic(true);

		$c->setAlias(ManagerRegistry::class, Registry::class)
			->setPublic(true);

		$c->autowire(PostRepository::class)->setPublic(true);
		$c->autowire(TagRepository::class)->setPublic(true);
		$c->autowire(UserRepository::class)->setPublic(true);

		$c->autowire(SmalldbPostRepository::class)->setPublic(true);

		return $c;
	}

	public static function createAnnotationMetadataConfiguration(ContainerInterface $container)
	{
		// Use autoloader to load annotations
		if (class_exists(AnnotationRegistry::class)) {
			AnnotationRegistry::registerUniqueLoader('class_exists');
		}

		$entityDir = dirname(__DIR__) . '/SymfonyDemo/Entity';
		if (!is_dir($entityDir)) {
			throw new \InvalidArgumentException('Entity directory does not exist: ' . $entityDir);  // @codeCoverageIgnore
		}

		$config = Setup::createAnnotationMetadataConfiguration([$entityDir], true, null, null, false);
		$config->setRepositoryFactory(new ContainerRepositoryFactory($container));
		$config->setNamingStrategy(new UnderscoreNamingStrategy());
		return $config;
	}

}
