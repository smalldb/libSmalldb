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

use Smalldb\StateMachine\CodeCooker\Cookbook;
use Smalldb\StateMachine\CodeCooker\DuplicateRecipeException;
use Smalldb\StateMachine\CodeCooker\Recipe\DtoRecipe;
use Smalldb\StateMachine\CodeCooker\Recipe\DummyRecipe;
use Smalldb\StateMachine\CodeCooker\RecipeLocator;
use Smalldb\StateMachine\Test\Example\Tag\TagData\TagData;


class ClassCookbookTest extends TestCase
{

	public function testCookbookAddRecipe()
	{
		$cookbook = new Cookbook();
		$recipeFoo = new DummyRecipe(["foo"]);
		$recipeBar = new DummyRecipe(["bar", "baz"]);

		$cookbook->addRecipe($recipeFoo);
		$cookbook->addRecipe($recipeBar);

		$recipes = $cookbook->getRecipes();
		$this->assertContainsEquals($recipeFoo, $recipes);
		$this->assertContainsEquals($recipeBar, $recipes);
	}


	public function testCookbookAddDuplicateRecipe()
	{
		$cookbook = new Cookbook();
		$recipeFoo = new DummyRecipe(["foo"]);
		$recipeBar = new DummyRecipe(["bar", "baz", "foo"]);

		$cookbook->addRecipe($recipeFoo);

		$this->expectException(DuplicateRecipeException::class);
		$cookbook->addRecipe($recipeBar);
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
		$this->assertContainsEquals($recipeFoo, $recipes);
		$this->assertContainsEquals($recipeBar, $recipes);
	}


	public function testCookbookGetTargetClassNames()
	{
		$cookbook = new Cookbook();
		$cookbook->addRecipe(new DummyRecipe(["foo"]));
		$cookbook->addRecipe(new DummyRecipe(["bar", "baz"]));

		$targetClassNames = iterator_to_array($cookbook->getAllTargetClassNames());
		$this->assertEquals(["foo", "bar", "baz"], $targetClassNames);
	}


	public function testLocateClasses()
	{
		$cg = new RecipeLocator($this->createExampleClassLocator());
		$foundClassCount = 0;

		foreach ($cg->getClassLocator()->getClasses() as $classname) {
			$this->assertClassOrInterfaceOrTraitExists($classname);
			$foundClassCount++;
		}

		$this->assertGreaterThanOrEqual(3, $foundClassCount,
			"There are at least three example entities and they should be found.");
	}


	public function testLocateRecipes()
	{
		$recipeLocator = new RecipeLocator($this->createExampleClassLocator());

		$cookbook = new Cookbook();
		$cookbook->addRecipes($recipeLocator->locateRecipes());

		$this->assertGreaterThanOrEqual(3, $cookbook->getRecipes(),
			"There are at least three example entities with recipes and they should be found.");

		$tagDataRecipe = $cookbook->findRecipe(TagData::class);
		$this->assertInstanceOf(DtoRecipe::class, $tagDataRecipe);
	}

}
