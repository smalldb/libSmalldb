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

use Smalldb\StateMachine\CodeGenerator\GeneratedClassAutoloader;
use Smalldb\StateMachine\CodeGenerator\SmalldbClassGenerator;
use Smalldb\StateMachine\Provider\LambdaProvider;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\SmalldbDefinitionBag;
use Smalldb\StateMachine\SmalldbDefinitionBagInterface;
use Smalldb\StateMachine\Utils\ClassLocator\ComposerClassLocator;
use Smalldb\StateMachine\Utils\ClassLocator\Psr4ClassLocator;
use Smalldb\StateMachine\Utils\ClassLocator\RealPathList;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;


class SmalldbExtension extends Extension
{
	protected const PROVIDER_CLASS = LambdaProvider::class;

	/**
	 * Create bundle configuration. Smalldb Bundle overrides this with
	 * an extended configuration class.
	 *
	 * @return Configuration
	 */
	public function getConfiguration(array $config, ContainerBuilder $container)
	{
		return new Configuration();
	}

	public function load(array $configs, ContainerBuilder $container)
	{
		// Get configuration
		$config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);
		if (!isset($config['class_generator'])) {
			// Stop if configuration is missing.
			return null;
		}

		// Define Smalldb entry point
		$smalldb = $container->autowire(Smalldb::class, Smalldb::class)
			->setPublic(true);

		// Load all state machine definitions
		$definitionBag = new SmalldbDefinitionBag();

		if (!empty($config['definition_classes'])) {
			$definitionClasses = $config['definition_classes'];
			$baseDir = $container->getParameter('kernel.project_dir');

			if (!empty($definitionClasses['exclude_dirs'])) {
				$excludeList = new RealPathList($baseDir, $definitionClasses['exclude_dirs']);
			} else {
				$excludeList = null;
			}

			if (!empty($definitionClasses['class_list'])) {
				$definitionBag->addFromAnnotatedClasses($config['definition_classes']['class_list']);
			}

			if (!empty($definitionClasses['psr4_dirs'])) {
				foreach ($definitionClasses['psr4_dirs'] as $dirConfig) {
					$definitionBag->addFromClassLocator(new Psr4ClassLocator($dirConfig['namespace'], $dirConfig['path'], $excludeList));
				}
			}

			if (!empty($definitionClasses['use_composer'])) {
				$excludeVendorDir = !empty($definitionClasses['ignore_vendor_dir']);
				$definitionBag->addFromClassLocator(new ComposerClassLocator($baseDir, $excludeList, $excludeVendorDir));
			}

		}

		// Register autoloader for generated classes
		$genNamespace = $config['class_generator']['namespace'];
		$genPath = $config['class_generator']['path'];
		$smalldb->addMethodCall('registerGeneratedClassAutoloader', [$genNamespace, $genPath]);
		$autoloader = new GeneratedClassAutoloader($genNamespace, $genPath);
		$autoloader->registerLoader();

		// Generate everything
		$this->generateClasses($container, $smalldb, $definitionBag,
			$genNamespace, $genPath);

		return $config;
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

			$realReferenceClass = $referenceClass ? $generator->generateReferenceClass($referenceClass, $definition) : null;
			$providerId = "smalldb.$machineType.provider";

			// Register the provider
			$this->registerProvider($container, $providerId, $machineType, $realReferenceClass,
				$repositoryClass ? new Reference($repositoryClass) : null,
				$transitionsClass ? new Reference($transitionsClass) : null,
				new Reference(SmalldbDefinitionBagInterface::class));

			// Register state machine type
			$smalldb->addMethodCall('registerMachineType', [new Reference($providerId), [$referenceClass]]);
		}

		// Generate static definition bag
		$generatedBag = $generator->generateDefinitionBag($definitionBag);
		$container->register(SmalldbDefinitionBagInterface::class, $generatedBag);
	}


	protected function registerProvider(ContainerBuilder $container, string $providerId, $machineType,
		?string $realReferenceClass, ?Reference $repositoryReference, ?Reference $transitionsReference,
		Reference $definitionBagReference): Definition
	{
		// Collect lazy-loaded references
		$serviceReferences = [];
		if ($repositoryReference) {
			$serviceReferences[LambdaProvider::REPOSITORY] = $repositoryReference;
		}
		if ($transitionsReference) {
			$serviceReferences[LambdaProvider::TRANSITIONS_DECORATOR] = $transitionsReference;
		}

		// Glue them together using a machine provider
		return $container->autowire($providerId, static::PROVIDER_CLASS)
			->addTag('container.service_locator')
			->addArgument($serviceReferences)
			->addArgument($machineType)
			->addArgument($realReferenceClass)
			->addArgument($definitionBagReference);
	}

}

