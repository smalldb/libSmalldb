<?php
/*
 * Copyright (c) 2017-2019, Josef Kufner  <josef@kufner.cz>
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

namespace Smalldb\StateMachine\SymfonyDI;

use Smalldb\StateMachine\CodeGenerator\SmalldbClassGenerator;
use Smalldb\StateMachine\Provider\LambdaProvider;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\SmalldbDefinitionBag;
use Smalldb\StateMachine\SmalldbDefinitionBagInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;


class SmalldbExtension extends Extension
{

	public function load(array $configs, ContainerBuilder $container)
	{
		// Get configuration
		$config = $this->processConfiguration(new Configuration(), $configs);

		// Define Smalldb entry point
		if ($container->has(Smalldb::class)) {
			$smalldb = $container->getDefinition(Smalldb::class);
		} else {
			$smalldb = $container->autowire(Smalldb::class, Smalldb::class)
				->setPublic(true);
		}

		// Load all state machine definitions
		$definitionBag = new SmalldbDefinitionBag();
		foreach ($config['machine_references'] as $refClass) {
			$definitionBag->addFromAnnotatedClass($refClass);
		}

		// Generate everything
		$this->generateClasses($container, $smalldb, $definitionBag,
			$config['class_generator']['namespace'], $config['class_generator']['path']);
	}


	private function generateClasses(ContainerBuilder $container, Definition $smalldb,
		SmalldbDefinitionBag $definitionBag,
		string $generatedCodeNamespace, string $generatedCodePath)
	{
		// Code generator
		$generator = new SmalldbClassGenerator($generatedCodeNamespace, $generatedCodePath);

		// Generate reference implementations and register providers
		foreach ($definitionBag->getAllDefinitions() as $machineType => $definition) {
			$referenceClass = $definition->getReferenceClass();
			$repositoryClass = $definition->getRepositoryClass();
			$transitionsClass = $definition->getTransitionsClass();

			// Collect lazy-loaded references
			$serviceReferences = [];
			if ($repositoryClass) {
				$serviceReferences[LambdaProvider::REPOSITORY] = new Reference($repositoryClass);
			}
			if ($transitionsClass) {
				$serviceReferences[LambdaProvider::TRANSITIONS_DECORATOR] = new Reference($transitionsClass);
			}

			$realReferenceClass = $referenceClass ? $generator->generateReferenceClass($referenceClass, $definition) : null;

			// Glue them together using a machine provider
			$providerId = "smalldb.$machineType.provider";
			$container->register($providerId, LambdaProvider::class)
				->addTag('container.service_locator')
				->addArgument($serviceReferences)
				->addArgument($machineType)
				->addArgument($realReferenceClass)
				->addArgument(new Reference(SmalldbDefinitionBagInterface::class));

			// Register state machine type
			$smalldb->addMethodCall('registerMachineType', [new Reference($providerId)]);
		}

		// Generate static definition bag
		$generatedBag = $generator->generateDefinitionBag($definitionBag);
		$container->register(SmalldbDefinitionBagInterface::class, $generatedBag);
	}

}

