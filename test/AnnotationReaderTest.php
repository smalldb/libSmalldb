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

use Doctrine\Common\Annotations\AnnotationException as DoctrineAnnotationException;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use Smalldb\StateMachine\AnnotationReader;
use Smalldb\StateMachine\Definition\StateDefinition;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Definition\TransitionDefinition;
use Smalldb\StateMachine\SqlExtension\Annotation\AnnotationException as SqlAnnotationException;
use Smalldb\StateMachine\Test\BadExample\ConflictingAnnotations;
use Smalldb\StateMachine\Test\BadExample\ConflictingAnnotations2;
use Smalldb\StateMachine\Test\BadExample\ConflictingAnnotationsWithId;
use Smalldb\StateMachine\Test\BadExample\ConflictingAnnotationsWithId2;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItem;
use Smalldb\StateMachine\Utils\DeepAnnotationReader;


class AnnotationReaderTest extends TestCase
{

	public function testCrudItem()
	{
		$reader = new AnnotationReader(CrudItem::class);
		$definition = $reader->getStateMachineDefinition();
		$this->assertInstanceOf(StateMachineDefinition::class, $definition);
		$this->assertEquals('crud-item', $definition->getMachineType());

		$existsState = $definition->getState('Exists');
		$this->assertInstanceOf(StateDefinition::class, $existsState);
		$this->assertInstanceOf(TransitionDefinition::class, $definition->getTransition('update', $existsState));
	}


	private function assertAnnotations1to4(array $annotations): void
	{
		$annotationClassNames = array_map(function($a) { return get_class($a); }, $annotations);
		$this->assertEquals([Annotation1::class, Annotation2::class, Annotation3::class, Annotation4::class], $annotationClassNames);
	}


	/**
	 * @throws DoctrineAnnotationException
	 * @throws ReflectionException
	 */
	public function testDeepReader()
	{
		$reader = new DeepAnnotationReader();

		$class = new \ReflectionClass(ChildAnnotatedClass::class);
		$this->assertAnnotations1to4($reader->getClassAnnotations($class));

		$method = $class->getMethod('foo');
		$this->assertAnnotations1to4($reader->getMethodAnnotations($method));

		$property = $class->getProperty('foo');
		$this->assertAnnotations1to4($reader->getPropertyAnnotations($property));

		$constant = $class->getReflectionConstant('FOO');
		$this->assertAnnotations1to4($reader->getConstantAnnotations($constant));
	}


	/**
	 * @dataProvider conflictingAnnotationClassesProvider
	 */
	public function testConflictingAnnotations(string $className)
	{
		$reader = new AnnotationReader($className);
		$this->expectException(SqlAnnotationException::class);
		$reader->getStateMachineDefinition();
	}

	public function conflictingAnnotationClassesProvider()
	{
		yield ConflictingAnnotations::class => [ConflictingAnnotations::class];
		yield ConflictingAnnotations2::class => [ConflictingAnnotations2::class];
		yield ConflictingAnnotationsWithId::class => [ConflictingAnnotationsWithId::class];
		yield ConflictingAnnotationsWithId2::class => [ConflictingAnnotationsWithId2::class];
	}

}


/**
 * @Annotation
 */
class Annotation1 {
}

/**
 * @Annotation
 */
class Annotation2 {
}

/**
 * @Annotation
 */
class Annotation3 {
}

/**
 * @Annotation
 */
class Annotation4 {
}

/**
 * Class ParentAnnotatedClass
 * @Annotation1
 * @Annotation2
 */
abstract class ParentAnnotatedClass {
	/**
	 * @Annotation1
	 * @Annotation2
	 */
	const FOO = 1;

	/**
	 * @Annotation1
	 * @Annotation2
	 */
	public $foo;

	/**
	 * @Annotation1
	 * @Annotation2
	 */
	abstract public function foo();
}

/**
 * Class ChildAnnotatedClass
 * @Annotation3
 * @Annotation4
 */
abstract class ChildAnnotatedClass extends ParentAnnotatedClass {
	/**
	 * @Annotation3
	 * @Annotation4
	 */
	const FOO = 2;

	/**
	 * @Annotation3
	 * @Annotation4
	 */
	public $foo;

	/**
	 * @Annotation3
	 * @Annotation4
	 */
	abstract public function foo();
}

