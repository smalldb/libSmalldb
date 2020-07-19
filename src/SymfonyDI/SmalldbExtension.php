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

use Smalldb\ClassLocator\ClassLocator;
use Smalldb\ClassLocator\CompositeClassLocator;
use Smalldb\CodeCooker\Chef;
use Smalldb\CodeCooker\Cookbook;
use Smalldb\CodeCooker\RecipeLocator;
use Smalldb\StateMachine\ClassGenerator\GeneratedClassAutoloader;
use Smalldb\StateMachine\ClassGenerator\SmalldbClassGenerator;
use Smalldb\StateMachine\Provider\LambdaProvider;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\SmalldbDefinitionBag;
use Smalldb\StateMachine\SmalldbDefinitionBagInterface;
use Smalldb\StateMachine\SmalldbDefinitionBagReader;
use Smalldb\ClassLocator\ComposerClassLocator;
use Smalldb\ClassLocator\Psr4ClassLocator;
use Smalldb\ClassLocator\RealPathList;
use Symfony\Component\Config\Resource\ReflectionClassResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;


class SmalldbExtension extends Extension implements CompilerPassInterface
{
	protected const PROVIDER_CLASS = LambdaProvider::class;

	protected array $config;


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
		$this->config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);

		// Define Smalldb entry point
		$smalldb = $container->autowire(Smalldb::class, Smalldb::class)
			->setPublic(true);

		$baseDir = $container->getParameter('kernel.project_dir');
		$classLocator = $this->createClassLocator($baseDir);

		// Code Cooker: Generate classes
		if (!empty($this->config['code_cooker']) && ($this->config['code_cooker']['enable'] ?? false)) {
			$cookbook = new Cookbook();
			$recipeLocator = new RecipeLocator($classLocator);
			$recipeLocator->onRecipeClass(function(\ReflectionClass $sourceClass) use ($container) {
				$container->addResource(new ReflectionClassResource($sourceClass));
			});
			$cookbook->addRecipes($recipeLocator->locateRecipes());
			$cheff = new Chef($cookbook, $classLocator);

			// FIXME: Cook on demand only
			$cheff->cookAllRecipes();

			if ($this->config['code_cooker']['enable_autoloader_generator'] ?? false) {
				$cheff->registerLoadingAutoloader();
			}
		}

		// Load all state machine definitions
		$definitionReader = new SmalldbDefinitionBagReader();
		$definitionReader->onDefinitionClass(function(\ReflectionClass $sourceClass) use ($container) {
			$container->addResource(new ReflectionClassResource($sourceClass));
		});
		if (empty($this->config['definition_classes'])) {
			$definitionReader->addFromClassLocator($classLocator);
		} else {
			$definitionReader->addFromAnnotatedClasses($this->config['definition_classes']);
		}

		// Register autoloader for generated classes
		$genNamespace = $this->config['class_generator']['namespace'] ?? 'Smalldb\\GeneratedCode\\';
		$genPath = $this->config['class_generator']['path'] ?? $container->getParameter('kernel.cache_dir') . '/smalldb';
		$smalldb->addMethodCall('registerGeneratedClassAutoloader', [$genNamespace, $genPath]);
		$autoloader = new GeneratedClassAutoloader($genNamespace, $genPath);
		$autoloader->registerLoader();

		// Generate everything
		$this->generateClasses($container, $smalldb, $definitionReader->getDefinitionBag(),
			$genNamespace, $genPath);
	}


	public function process(ContainerBuilder $container)
	{
		// ...
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


	protected function createClassLocator(string $baseDir): ClassLocator
	{
		$classLocator = new CompositeClassLocator();

		if (!empty($this->config['class_locator'])) {
			$classLocatorConfig = $this->config['class_locator'];

			if (!empty($classLocatorConfig['include_dirs'])) {
				$includeList = new RealPathList($baseDir, $classLocatorConfig['include_dirs']);
			} else {
				$includeList = null;
			}

			if (!empty($classLocatorConfig['exclude_dirs'])) {
				$excludeList = new RealPathList($baseDir, $classLocatorConfig['exclude_dirs']);
			} else {
				$excludeList = null;
			}

			if (!empty($classLocatorConfig['psr4_dirs'])) {
				foreach ($classLocatorConfig['psr4_dirs'] as $namespace => $dir) {
					$classLocator->addClassLocator(new Psr4ClassLocator($namespace, $dir));
				}
			}

			if (!empty($classLocatorConfig['use_composer'])) {
				$excludeVendorDir = !empty($classLocatorConfig['ignore_vendor_dir']);
				$classLocator->addClassLocator(new ComposerClassLocator($baseDir, $includeList, $excludeList, $excludeVendorDir));
			}
		} else {
			$classLocator->addClassLocator(new ComposerClassLocator($baseDir, [], [], true));
		}

		return $classLocator;
	}

}

