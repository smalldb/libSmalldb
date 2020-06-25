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

use Smalldb\StateMachine\CodeCooker\Chef;
use Smalldb\StateMachine\CodeCooker\Cookbook;
use Smalldb\StateMachine\CodeCooker\Recipe\ClassRecipe;
use Smalldb\StateMachine\CodeCooker\RecipeLocator;
use Smalldb\StateMachine\Test\Example\Tag\TagData\TagData;
use Smalldb\StateMachine\Test\Example\User\UserData\UserDataImmutable;


class ClassCheffTest extends TestCase
{

	private function createExampleCookbook(): Cookbook
	{
		$recipeLocator = new RecipeLocator($this->createExampleClassLocator());

		$cookbook = new Cookbook();
		$cookbook->addRecipes($recipeLocator->locateRecipes());

		return $cookbook;
	}


	public function testCookbookSetup()
	{
		$cookbook = $this->createExampleCookbook();
		$this->assertInstanceOf(ClassRecipe::class, $cookbook->findRecipe(TagData::class));
	}


	public function testDeleteGeneratedClassesAndCookThemFresh()
	{
		$cookbook = $this->createExampleCookbook();
		$classLocator = $this->createExampleClassLocator();
		$chef = new Chef($cookbook, $classLocator);

		// Remove everything
		$chef->deleteAllTargetClasses();
		$this->assertFileNotExists(__DIR__ . '/Example/Tag/TagData/TagData.php');

		// Remove one of the target directories to test that it will get created again
		$tagDataDir = __DIR__ . '/Example/Tag/TagData';
		if (is_dir($tagDataDir)) {
			rmdir($tagDataDir);
		}
		$this->assertDirectoryNotExists(__DIR__ . '/Example/Tag/TagData');

		// Cook all the classes again
		$chef->cookAllRecipes();
		$this->assertFileExists(__DIR__ . '/Example/Tag/TagData/TagData.php');
		$this->assertClassOrInterfaceOrTraitExists(TagData::class);
	}

}
