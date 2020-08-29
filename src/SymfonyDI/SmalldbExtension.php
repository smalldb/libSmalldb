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
use Smalldb\CodeCooker\Recipe\ClassRecipe;
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
use Smalldb\StateMachine\SourcesExtension\Definition\SourceClassFile;
use Smalldb\StateMachine\SourcesExtension\Definition\SourcesExtension;
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

		// Setup Class Locator
		$baseDir = $container->getParameter('kernel.project_dir');
		$classLocator = $this->createClassLocator($baseDir, $this->config['class_locator'] ?? null);
		$classLocator->setBrokenClassHandler($this->brokenClassLogger = new BrokenClassLogger());

		// Register autoloader for generated classes
		$genNamespace = $this->config['class_generator']['namespace'] ?? 'Smalldb\\GeneratedCode\\';
		$genPath = $this->config['class_generator']['path'] ?? $container->getParameter('kernel.cache_dir') . '/smalldb';
		$autoloader = new GeneratedClassAutoloader($genNamespace, $genPath);
		$autoloader->registerLoader();

		// Define Smalldb entry point
		$smalldb = $container->autowire(Smalldb::class, Smalldb::class)
			->setFactory(Smalldb::class . '::createWithGeneratedClassAutoloader')
			->setArguments([$genNamespace, $genPath])
			->setPublic(true);


		// Load all state machine definitions
		$definitionReader = new SmalldbDefinitionBagReader();
		if (empty($this->config['definition_classes'])) {
			$definitionReader->addFromClassLocator($classLocator);
		} else {
			$definitionReader->addFromAnnotatedClasses($this->config['definition_classes']);
		}
		$definitionBag = $definitionReader->getDefinitionBag();

		// Generate everything
		$this->generateClasses($container, $smalldb, $definitionBag, $genNamespace, $genPath);

		// Register source files for automatic container reload
		$this->registerSources($definitionBag, $container);

		// Register Guard
		$container->autowire(TransitionGuard::class, SimpleTransitionGuard::class)
			->setArguments([
				SimpleTransitionGuard::compileTransitionPredicatesSymfony($definitionBag, $container),
				isset($this->config['access_control']['default_allow'])
					? (bool) $this->config['access_control']['default_allow'] : true,
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


	protected function createClassLocator(string $baseDir, ?array $config): ClassLocator
	{
		if (!empty($config)) {
			$classLocator = new CompositeClassLocator();

			if (!empty($config['include_dirs'])) {
				$includeList = new RealPathList($baseDir, $config['include_dirs']);
			} else {
				$includeList = null;
			}

			if (!empty($config['exclude_dirs'])) {
				$excludeList = new RealPathList($baseDir, $config['exclude_dirs']);
			} else {
				$excludeList = null;
			}

			if (!empty($config['psr4_dirs'])) {
				foreach ($config['psr4_dirs'] as $namespace => $dir) {
					$classLocator->addClassLocator(new Psr4ClassLocator($namespace, $dir));
				}
			}

			if (!empty($config['use_composer'])) {
				$excludeVendorDir = !empty($config['ignore_vendor_dir']);
				$classLocator->addClassLocator(new ComposerClassLocator($baseDir, $includeList, $excludeList, $excludeVendorDir));
			}

			return $classLocator;
		} else {
			return new ComposerClassLocator($baseDir, [], [], true);
		}
	}


	private function registerSources(SmalldbDefinitionBag $definitionBag, ContainerBuilder $container)
	{
		foreach ($definitionBag->getAllDefinitions() as $definition) {
			if (($ext = $definition->findExtension(SourcesExtension::class))) {
				foreach ($ext->getSourceFiles() as $source) {
					$container->addResource(new FileResource($source->getFilename()));
					if ($source instanceof SourceClassFile) {
						$container->addResource(new ReflectionClassResource(new \ReflectionClass($source->getClassname())));
					}
				}
			}
		}
	}


	private function createCheff(ClassLocator $classLocator, ContainerBuilder $container): Chef
	{
		$cookbook = new Cookbook();
		$recipeLocator = new RecipeLocator($classLocator);
		$cookbook->addRecipes($recipeLocator->locateRecipes());
		foreach ($cookbook->getRecipes() as $recipe) {
			if ($recipe instanceof ClassRecipe) {
				$container->addResource(new ReflectionClassResource($recipe->getSourceClass()));
			}
		}
		return new Chef($cookbook, $classLocator);
	}

}

