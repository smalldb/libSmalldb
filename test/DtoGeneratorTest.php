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
use Smalldb\StateMachine\CodeGenerator\Annotation\GenerateDTO;
use Smalldb\StateMachine\CodeGenerator\AnnotationHandler;
use Smalldb\StateMachine\CodeGenerator\CodeGenerator;
use Smalldb\StateMachine\CodeGenerator\DtoGenerator;
use Smalldb\StateMachine\Test\DtoGeneratorExample\SupervisorProcess\SupervisorProcess;
use Smalldb\StateMachine\Test\DtoGeneratorExample\Tag as TagProperties;
use Smalldb\StateMachine\Test\DtoGeneratorExample\Tag\Tag;
use Smalldb\StateMachine\Test\DtoGeneratorExample\Tag\TagImmutable;
use Smalldb\StateMachine\Test\DtoGeneratorExample\Tag\TagMutable;
use Smalldb\StateMachine\Utils\ClassLocator\Psr4ClassLocator;


class DtoGeneratorTest extends TestCase
{

	public function testLocateClasses()
	{
		$cg = new CodeGenerator();
		$cg->addClassLocator(new Psr4ClassLocator(__NAMESPACE__ . '\\DtoGeneratorExample\\', __DIR__ . '/DtoGeneratorExample', []));
		$foundClassCount = 0;

		foreach ($cg->locateClasses() as $classname) {
			$this->assertClassOrInterfaceOrTraitExists($classname);
			$foundClassCount++;
		}

		$this->assertGreaterThanOrEqual(3, $foundClassCount);
	}


	/**
	 * @depends testLocateClasses
	 */
	public function testDeleteGeneratedClasses()
	{
		$cg = new CodeGenerator();
		$cg->addClassLocator(new Psr4ClassLocator(__NAMESPACE__ . '\\DtoGeneratorExample\\', __DIR__ . '/DtoGeneratorExample', []));
		$cg->deleteGeneratedClasses();

		$this->assertFileNotExists(__DIR__ . '\\DtoGeneratorExample\\Tag\\Tag.php');
	}


	/**
	 * @depends testDeleteGeneratedClasses
	 */
	public function testCodeGenerator()
	{
		$handlerMock = $this->getMockBuilder(AnnotationHandler::class)->getMock();
		$handlerMock->method('getSupportedAnnotations')->willReturn([GenerateDTO::class]);
		$handlerMock->expects($this->once())->method('handleClassAnnotation');

		$cg = new CodeGenerator();
		/** @noinspection PhpParamsInspection */
		$cg->addAnnotationHandler($handlerMock);
		$cg->processClass(new ReflectionClass(TagProperties::class));

		$this->assertClassOrInterfaceOrTraitExists(Tag::class);
	}


	/**
	 * @depends testDeleteGeneratedClasses
	 */
	public function testGenerateTagDto()
	{
		$g = new DtoGenerator();
		$generatedClasses = $g->generateDtoClasses(new ReflectionClass(TagProperties::class));

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


	/**
	 * @depends testDeleteGeneratedClasses
	 */
	public function testEntireCodeGeneratorFlow()
	{
		$cg = new CodeGenerator();
		$cg->addClassLocator(new Psr4ClassLocator(__NAMESPACE__ . '\\DtoGeneratorExample\\', __DIR__ . '/DtoGeneratorExample', []));
		$cg->addAnnotationHandler(new DtoGenerator($cg->getAnnotationReader()));
		$cg->processClasses();

		$this->assertClassOrInterfaceExists(SupervisorProcess::class);
	}


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
