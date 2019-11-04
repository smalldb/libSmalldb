<?php declare(strict_types = 1);
/*
 * Copyright (c) 2019, Josef Kufner  <josef@kufner.cz>
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

use PHPUnit\Framework\TestCase;
use Smalldb\StateMachine\CodeGenerator\DefinitionBagGenerator;
use Smalldb\StateMachine\CodeGenerator\SmalldbClassGenerator;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\SmalldbDefinitionBagInterface;
use Smalldb\StateMachine\SmalldbDefinitionBagReader;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItem;
use Smalldb\StateMachine\Test\Example\Post\Post;
use Smalldb\StateMachine\Test\Example\Tag\Tag;
use Smalldb\StateMachine\Test\Example\User\User;
use Smalldb\StateMachine\Test\TestTemplate\TestOutput;


class DefinitionBagGeneratorTest extends TestCase
{

	public function testDefinitionBagGenerator()
	{
		$origBagReader = new SmalldbDefinitionBagReader();
		$origBagReader->addFromAnnotatedClass(CrudItem::class);
		$origBagReader->addFromAnnotatedClass(Post::class);
		$origBagReader->addFromAnnotatedClass(Tag::class);
		$origBagReader->addFromAnnotatedClass(User::class);
		$origBag = $origBagReader->getDefinitionBag();

		$out = new TestOutput();

		$scg = new SmalldbClassGenerator('Smalldb\\GeneratedCode\\', $out->mkdir('generated'));
		$generator = new DefinitionBagGenerator($scg);

		// Setup Smalldb & autoloader for generated classes
		$smalldb = new Smalldb();
		$smalldb->registerGeneratedClassAutoloader($scg->getClassNamespace(), $scg->getClassDirecotry(), true);

		$generatedClass = $generator->generateDefinitionBagClass('Smalldb\\GeneratedCode\\GeneratedDefinitionBag_BasicTest', $origBag);
		$this->assertTrue(class_exists($generatedClass));

		/** @var SmalldbDefinitionBagInterface $generatedBag */
		$generatedBag = new $generatedClass();
		$machineTypes = $origBag->getAllMachineTypes();
		$this->assertEquals($origBag->getAllMachineTypes(), $generatedBag->getAllMachineTypes());
		foreach ($machineTypes as $machineType) {
			$this->assertEquals($origBag->getDefinition($machineType), $generatedBag->getDefinition($machineType));
		}
	}

}
