<?php declare(strict_types = 1);
/*
 * Copyright (c) 2020, Josef Kufner  <josef@kufner.cz>
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

namespace Smalldb\StateMachine\CodeCooker;

use Smalldb\StateMachine\Utils\ClassLocator\ClassLocator;


class Chef
{
	private Cookbook $cookbook;

	private ?\Closure $autoloadClosure = null;
	private ClassLocator $classLocator;
	private array $cookedTargetClassNames = [];


	public function __construct(Cookbook $cookbook, ClassLocator $classLocator)
	{
		$this->cookbook = $cookbook;
		$this->classLocator = $classLocator;
	}


	public function __destruct()
	{
		$this->unregisterAutoloader();
	}


	public function cookAllRecipes()
	{
		foreach ($this->cookbook->getRecipes() as $recipe) {
			$recipe->cookRecipe($this->classLocator);
		}
	}


	private function getTargetFileNames(iterable $targetClassNames): iterable
	{
		foreach ($targetClassNames as $targetClassName) {
			yield $this->classLocator->mapClassNameToFileName($targetClassName);
		}
	}


	public function deleteAllTargetClasses()
	{
		foreach ($this->getTargetFileNames($this->cookbook->getAllTargetClassNames()) as $targetFileName) {
			if (file_exists($targetFileName)) {
				unlink($targetFileName);
			}
		}
	}


	public function registerAutoloader()
	{
		// Register default autoloader implementation
		$this->registerPassThroughAutoloader();
	}


	public function registerPassThroughAutoloader()
	{
		if ($this->autoloadClosure) {
			throw new \LogicException("Autoloader is already registered.");
		}

		$this->autoloadClosure = function (string $className) {
			if (isset($this->cookedTargetClassNames[$className])) {
				return false;
			}

			$recipe = $this->cookbook->findRecipe($className);
			if ($recipe) {
				$recipe->cookRecipe($this->classLocator);
				$targetClassNames = $recipe->getTargetClassNames();
				foreach ($targetClassNames as $targetClassName) {
					$this->cookedTargetClassNames[$targetClassName] = true;
				}
			}

			// Pass-through to default autoloader -- the class(es) should be generated into the standard location.
			// TODO: Deal with unexpected caching (Composer's class-map optimization).
			return false;
		};

		spl_autoload_register($this->autoloadClosure, true, true);
	}


	public function registerLoadingAutoloader()
	{
		if ($this->autoloadClosure) {
			throw new \LogicException("Autoloader is already registered.");
		}

		$this->autoloadClosure = function (string $className) {
			$recipe = $this->cookbook->findRecipe($className);
			if ($recipe) {
				$recipe->cookRecipe($this->classLocator);
				$targetClassNames = $recipe->getTargetClassNames();

				// Include all the generated files
				$targetFileNames = $this->getTargetFileNames($targetClassNames);
				foreach ($targetFileNames as $targetFileName) {
					require $targetFileName;
				}
				return true;
			}
			return false;
		};

		spl_autoload_register($this->autoloadClosure, true, true);
	}


	public function unregisterAutoloader()
	{
		if ($this->autoloadClosure) {
			spl_autoload_unregister($this->autoloadClosure);
			$this->autoloadClosure = null;
		}
	}

}

