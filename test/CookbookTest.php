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

namespace Smalldb\StateMachine\Test;

use PHPStan\Testing\TestCase;
use Smalldb\StateMachine\CodeGenerator\Cookbook;
use Smalldb\StateMachine\CodeGenerator\Recipe\DtoRecipe;
use Smalldb\StateMachine\CodeGenerator\Recipe\DummyRecipe;
use Smalldb\StateMachine\CodeGenerator\RecipeLocator;
use Smalldb\StateMachine\Test\Example\Tag\TagData\TagData;
use Smalldb\StateMachine\Utils\ClassLocator\Psr4ClassLocator;


class CookbookTest extends TestCase
{

	public function testCookbookAddRecipe()
	{
		$cookbook = new Cookbook();
		$recipeFoo = new DummyRecipe(["foo"]);
		$recipeBar = new DummyRecipe(["bar", "baz"]);

		$cookbook->addRecipe($recipeFoo);
		$cookbook->addRecipe($recipeBar);

		$recipes = $cookbook->getRecipes();
		$this->assertContains($recipeFoo, $recipes);
		$this->assertContains($recipeBar, $recipes);
	}


	public function testCookbookAddRecipes()
	{
		$cookbook = new Cookbook();
		$recipeFoo = new DummyRecipe(["foo"]);
		$recipeBar = new DummyRecipe(["bar", "baz"]);

		$generator = function () use ($recipeFoo, $recipeBar) {
			yield $recipeFoo;
			yield $recipeBar;
		};

		$cookbook->addRecipes($generator());

		$recipes = $cookbook->getRecipes();
		$this->assertContains($recipeFoo, $recipes);
		$this->assertContains($recipeBar, $recipes);
	}


	public function testLocateClasses()
	{
		$cg = new RecipeLocator();
		$cg->addClassLocator(new Psr4ClassLocator(__NAMESPACE__ . '\\Example\\', __DIR__ . '/Example', []));
		$foundClassCount = 0;

		foreach ($cg->locateClasses() as $classname) {
			$this->assertClassOrInterfaceOrTraitExists($classname);
			$foundClassCount++;
		}

		$this->assertGreaterThanOrEqual(3, $foundClassCount,
			"There are at least three example entities and they should be found.");
	}


	public function testLocateRecipes()
	{
		$recipeLocator = new RecipeLocator();
		$recipeLocator->addClassLocator(new Psr4ClassLocator(__NAMESPACE__ . '\\Example\\', __DIR__ . '/Example', []));

		$cookbook = new Cookbook();
		$cookbook->addRecipes($recipeLocator->locateRecipes());

		$this->assertGreaterThanOrEqual(3, $cookbook->getRecipes(),
			"There are at least three example entities with recipes and they should be found.");

		$tagDataRecipe = $cookbook->findRecipe(TagData::class);
		$this->assertInstanceOf(DtoRecipe::class, $tagDataRecipe);
	}


	private function assertClassOrInterfaceOrTraitExists(string $className)
	{
		$this->assertTrue(class_exists($className) || interface_exists($className) || trait_exists($className),
			"Class or interface or trait $className does not exist.");
	}

}
