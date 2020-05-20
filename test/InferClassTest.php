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
use ReflectionClass;
use Smalldb\StateMachine\InferClass\InferClass;
use Smalldb\StateMachine\InferClass\SmalldbEntityGenerator;
use Smalldb\StateMachine\Test\EntityGeneratorExample\Tag as TagProperties;
use Smalldb\StateMachine\Test\EntityGeneratorExample\Tag\Tag;
use Smalldb\StateMachine\Test\EntityGeneratorExample\Tag\TagImmutable;
use Smalldb\StateMachine\Test\EntityGeneratorExample\Tag\TagMutable;
use Smalldb\StateMachine\Test\Example\SupervisorProcess\SupervisorProcessData;
use Smalldb\StateMachine\Utils\ClassLocator\Psr4ClassLocator;


class InferClassTest extends TestCase
{

	public function testLocateClasses()
	{
		$this->markTestSkipped();

		$inferClass = new InferClass();
		$inferClass->addClassLocator(new Psr4ClassLocator(__NAMESPACE__ . '\\Example\\', __DIR__ . '/Example', []));
		$inferClass->addClassLocator(new Psr4ClassLocator(__NAMESPACE__ . '\\EntityGeneratorExample\\', __DIR__ . '/EntityGeneratorExample', []));
		$inferClass->addClassLocator(new Psr4ClassLocator(__NAMESPACE__ . '\\SymfonyDemo\\', __DIR__ . '/SymfonyDemo', []));
		$foundClassCount = 0;

		foreach ($inferClass->locateClasses() as $classname) {
			$this->assertClassOrInterfaceExists($classname);
			$foundClassCount++;
		}

		$this->assertGreaterThanOrEqual(3, $foundClassCount);
	}


	public function testInferFromTagClass()
	{
		$eg = new SmalldbEntityGenerator();
		$generatedClasses = $eg->inferEntityClasses(new ReflectionClass(TagProperties::class));

		$this->assertNotEmpty($generatedClasses);
		foreach ($generatedClasses as $generatedClass) {
			$this->assertClassOrInterfaceOrTraitExists($generatedClass);
		}

		$tag = new TagMutable();
		$tag->setId(1);
		$tag->setName('Foo Bar');

		$copiedTag = new TagImmutable($tag);
		$copiedTag2 = new TagMutable($copiedTag);

		$this->assertInstanceOf(Tag::class, $tag);
		$this->assertInstanceOf(Tag::class, $copiedTag);
		$this->assertInstanceOf(Tag::class, $copiedTag2);
		$this->assertEquals($tag, $copiedTag2);

		$slug = $copiedTag2->getSlug();
		$this->assertEquals('foo-bar', $slug);

	}


	// TODO: Add a test with Symfony Forms
	//      https://symfony.com/doc/current/form/data_mappers.html


	public function testProcessSupervisorClass()
	{
		$this->markTestSkipped();

		$supervisorClass = new ReflectionClass(SupervisorProcessData::class);
		$namespace = $supervisorClass->getNamespaceName();
		$directory = dirname($supervisorClass->getFileName());

		$inferClass = new InferClass();
		$inferClass->addClassLocator(new Psr4ClassLocator($namespace, $directory, []));
		$inferClass->processClasses();

		// Check for the generated classes
		$this->assertClassExists(SupervisorProcessData\SupervisorProcessDataImmutable::class);
	}


	/*
	public function testInferEverything()
	{
		$inferClass = new InferClass();
		$inferClass->addClassLocator(new Psr4ClassLocator(__NAMESPACE__, __DIR__);
		$inferClass->processClasses();

		$this->assertClassOrInterfaceExists(Post::class);
	}
	*/


	private function assertClassExists(string $className)
	{
		$this->assertTrue(class_exists($className), "Class $className does not exist.");
	}

	private function assertClassOrInterfaceExists(string $className)
	{
		$this->assertTrue(class_exists($className) || interface_exists($className),
			"Class or interface $className does not exist.");
	}

	private function assertClassOrInterfaceOrTraitExists(string $className)
	{
		$this->assertTrue(class_exists($className) || interface_exists($className) || trait_exists($className),
			"Class or interface or trait $className does not exist.");
	}

}
