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

use Smalldb\StateMachine\CodeCooker\Recipe\ClassRecipe;


class Cookbook
{
	/** @var ClassRecipe[] */
	private array $recipes;

	private array $knownClasses = [];


	public function __construct(array $recipes = [])
	{
		$this->recipes = $recipes;
	}


	public function addRecipes(iterable $recipes): void
	{
		foreach ($recipes as $recipe) {
			$this->addRecipe($recipe);
		}
	}


	public function addRecipe(ClassRecipe $recipe): void
	{
		$this->recipes[] = $recipe;
		foreach ($recipe->getTargetClassNames() as $targetClassName) {
			if (isset($this->knownClasses[$targetClassName])) {
				throw new DuplicateRecipeException("Recipe for class $targetClassName is already defined.");
			}
			$this->knownClasses[$targetClassName] = $recipe;
		}
	}


	public function getRecipes(): array
	{
		return $this->recipes;
	}


	public function findRecipe(string $targetClassName): ?ClassRecipe
	{
		return $this->knownClasses[$targetClassName] ?? null;
	}


	public function getAllTargetClassNames(): \Generator
	{
		foreach ($this->getRecipes() as $recipe) {
			foreach ($recipe->getTargetClassNames() as $targetClassName) {
				yield $targetClassName;
			}
		}
	}

}
