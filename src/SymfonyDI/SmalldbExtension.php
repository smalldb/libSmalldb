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

use Smalldb\ClassLocator\BrokenClassLogger;
use Smalldb\ClassLocator\ClassLocator;
use Smalldb\ClassLocator\CompositeClassLocator;
use Smalldb\CodeCooker\Chef;
use Smalldb\CodeCooker\Cookbook;
use Smalldb\CodeCooker\RecipeLocator;
use Smalldb\StateMachine\AccessControlExtension\SimpleTransitionGuard;
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
use Smalldb\StateMachine\Transition\TransitionGuard;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Config\Resource\ReflectionClassResource;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;


class SmalldbExtension extends Extension implements CompilerPassInterface
{
	protected const PROVIDER_CLASS = LambdaProvider::class;
	protected array $config;
	private BrokenClassLogger $brokenClassLogger;


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
		$classLocator->setBrokenClassHandler($this->brokenClassLogger = new BrokenClassLogger());

		// Code Cooker: Generate classes
		if (!empty($this->config['code_cooker']) && ($this->config['code_cooker']['enable'] ?? false)) {
			$cookbook = new Cookbook();
			$recipeLocator = new RecipeLocator($classLocator);
			$recipeLocator->onRecipeClass(function (\ReflectionClass $sourceClass) use ($container) {
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

		// Register autoloader for generated classes
		$genNamespace = $this->config['class_generator']['namespace'] ?? 'Smalldb\\GeneratedCode\\';
		$genPath = $this->config['class_generator']['path'] ?? $container->getParameter('kernel.cache_dir') . '/smalldb';
		$smalldb->addMethodCall('registerGeneratedClassAutoloader', [$genNamespace, $genPath]);
		$autoloader = new GeneratedClassAutoloader($genNamespace, $genPath);
		$autoloader->registerLoader();

		// Load all state machine definitions
		$definitionReader = new SmalldbDefinitionBagReader();
		$definitionReader->onDefinitionClass(function (\ReflectionClass $sourceClass) use ($container) {
			$container->addResource(new ReflectionClassResource($sourceClass));
		});
		if (empty($this->config['definition_classes'])) {
			$definitionReader->addFromClassLocator($classLocator);
		} else {
			$definitionReader->addFromAnnotatedClasses($this->config['definition_classes']);
		}
		$definitionBag = $definitionReader->getDefinitionBag();

		// Generate everything
		$this->generateClasses($container, $smalldb, $definitionBag, $genNamespace, $genPath);

		// Register Guard
		$container->autowire(TransitionGuard::class, SimpleTransitionGuard::class)
			->setArguments([
				SimpleTransitionGuard::compileTransitionPredicatesSymfony($definitionBag, $container),
				!empty($this->config['access_control']['default_allow'])
			]);
	}


	public function process(ContainerBuilder $container)
	{
		foreach ($this->brokenClassLogger->getBrokenClasses()
			as ['className' => $className, 'fileAbsPath' => $fileAbsPath, 'reason' => $reason])
		{
			$container->log($this, sprintf("Ignoring a broken class \"%s\": %s", $className, $reason->getMessage()));
			$container->addResource(new FileResource($fileAbsPath));  // Rebuild the container if the broken file gets fixed.
		}

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
				new Reference(SmalldbDefinitionBagInterface::class),
				$transitionsClass ? new Reference($transitionsClass) : null,
				$repositoryClass ? new Reference($repositoryClass) : null);

			// Register state machine type
			$smalldb->addMethodCall('registerMachineType', [new Reference($providerId), [$referenceClass]]);
		}

		// Generate static definition bag
		$generatedBag = $generator->generateDefinitionBag($definitionBag);
		$container->register(SmalldbDefinitionBagInterface::class, $generatedBag);
	}


	protected function registerProvider(ContainerBuilder $container, string $providerId, $machineType,
		?string $realReferenceClass, Reference $definitionBagReference,
		?Reference $transitionsReference, ?Reference $repositoryReference): Definition
	{
		// Glue them together using a machine provider
		return $container->autowire($providerId, static::PROVIDER_CLASS)
			->setFactory(LambdaProvider::class . '::createWithDefinitionBag')
			->setArguments([
				$machineType,
				$realReferenceClass,
				$definitionBagReference,
				$transitionsReference ? new ServiceClosureArgument($transitionsReference) : null,
				$repositoryReference ? new ServiceClosureArgument($repositoryReference) : null,
			]);
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

