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
use Smalldb\StateMachine\CodeGenerator\InferClass\InferClass;
use Smalldb\StateMachine\CodeGenerator\InferClass\Psr4ClassLocator;
use Smalldb\StateMachine\Test\Example\SupervisorProcess\SupervisorProcessData;


class InferClassTest extends TestCase
{

	public function testLocateClasses()
	{
		$inferClass = new InferClass();
		$inferClass->addClassLocator(new Psr4ClassLocator(__NAMESPACE__ . '\\Example\\', __DIR__ . '/Example'));
		$inferClass->addClassLocator(new Psr4ClassLocator(__NAMESPACE__ . '\\SymfonyDemo\\', __DIR__ . '/SymfonyDemo'));

		foreach ($inferClass->locateClasses() as $classname) {
			$this->assertClassOrInterfaceExists($classname);
		}
	}


	public function testProcessSupervisorClass()
	{
		$supervisorClass = new ReflectionClass(SupervisorProcessData::class);
		$namespace = $supervisorClass->getNamespaceName();
		$directory = dirname($supervisorClass->getFileName());

		$inferClass = new InferClass();
		$inferClass->addClassLocator(new Psr4ClassLocator($namespace, $directory));
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


	private function assertClassOrInterfaceExists(string $className)
	{
		$this->assertTrue(class_exists($className) || interface_exists($className),
			"Class or interface $className does not exist.");
	}


	private function assertClassExists(string $className)
	{
		$this->assertTrue(class_exists($className), "Class $className does not exist.");
	}

}
