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
use Smalldb\StateMachine\CodeGenerator\ReferenceClassGenerator;
use Smalldb\StateMachine\CodeGenerator\SmalldbClassGenerator;
use Smalldb\StateMachine\Provider\LambdaProvider;
use Smalldb\StateMachine\ReferenceDataSource\DummyDataSource;
use Smalldb\StateMachine\ReferenceDataSource\NotExistsException;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\SmalldbDefinitionBagReader;
use Smalldb\StateMachine\Test\Example\Post\Post;
use Smalldb\StateMachine\Test\TestTemplate\TestOutput;


class ReferenceClassGeneratorTest extends TestCase
{

	public function testReferenceGenerator()
	{
		$out = new TestOutput();
		$namespace = 'Smalldb\\GeneratedCode';
		$scg = new SmalldbClassGenerator($namespace, $out->mkdir('generated'));

		// Setup Smalldb & autoloader for generated classes
		$smalldb = new Smalldb();
		$smalldb->registerGeneratedClassAutoloader($scg->getClassNamespace(), $scg->getClassDirecotry(), true);

		$origClass = Post::class;

		$definitionReader = new SmalldbDefinitionBagReader();
		$definition = $definitionReader->addFromAnnotatedClass($origClass);

		$generator = new ReferenceClassGenerator($scg);
		$newClass = $generator->generateReferenceClass($origClass, $definition);

		// Try to create a dummy null reference
		$provider = new LambdaProvider();
		$dataSource = new DummyDataSource();
		/** @var Post $newClassInstance */
		$newClassInstance = new $newClass($smalldb, $provider, $dataSource, null);

		// The new reference must implement the original class, so we can use the original for type hints.
		$this->assertInstanceOf($origClass, $newClassInstance);

		// DummyDataSource: Try to load state of the machine (DummyDataSource always returns NotExists)
		$this->assertEquals('', $newClassInstance->getState());

		// DummyDataSource: Data should not be available in Not Exists state
		$this->expectException(NotExistsException::class);
		$this->assertEquals(null, $newClassInstance->getTitle());
	}

}
