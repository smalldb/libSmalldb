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

use Smalldb\StateMachine\Annotation\AbstractIncludeAnnotation;
use Smalldb\StateMachine\Definition\AnnotationReader\AnnotationReader;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilderFactory;
use Smalldb\StateMachine\Definition\StateDefinition;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Definition\TransitionDefinition;
use Smalldb\StateMachine\SqlExtension\AnnotationException as SqlAnnotationException;
use Smalldb\StateMachine\Test\BadExample\ApplyToMachineDefinition;
use Smalldb\StateMachine\Test\BadExample\ConflictingAnnotations;
use Smalldb\StateMachine\Test\BadExample\ConflictingAnnotations2;
use Smalldb\StateMachine\Test\BadExample\ConflictingAnnotationsWithId;
use Smalldb\StateMachine\Test\BadExample\ConflictingAnnotationsWithId2;
use Smalldb\StateMachine\Test\BadExample\MultipleConstants;
use Smalldb\StateMachine\Test\BadExample\MultipleStateAnnotations;
use Smalldb\StateMachine\Test\BadExample\PropertyDefinition;
use Smalldb\StateMachine\Test\BadExample\PropertyGetterDefinition;
use Smalldb\StateMachine\Test\BadExample\TransitionWithoutDefinition;
use Smalldb\StateMachine\Test\BadExample\TransitionWithoutStates;
use Smalldb\StateMachine\Test\BadExample\TransitionWithTooManyStates;
use Smalldb\StateMachine\Test\BadExample\UseReferenceTrait;
use Smalldb\StateMachine\Test\Example\Annotation\ApplyToEverything;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItem;


class AnnotationReaderTest extends TestCase
{

	public function testCrudItem()
	{
		$reader = new AnnotationReader(StateMachineDefinitionBuilderFactory::createDefaultFactory());
		$definition = $reader->getStateMachineDefinition(new \ReflectionClass(CrudItem::class));
		$this->assertInstanceOf(StateMachineDefinition::class, $definition);
		$this->assertEquals('crud-item', $definition->getMachineType());

		$existsState = $definition->getState('Exists');
		$this->assertInstanceOf(StateDefinition::class, $existsState);
		$this->assertInstanceOf(TransitionDefinition::class, $definition->getTransition('update', $existsState));
	}


	/**
	 * @dataProvider conflictingAnnotationClassesProvider
	 */
	public function testConflictingAnnotations(string $className)
	{
		$reader = new AnnotationReader(StateMachineDefinitionBuilderFactory::createDefaultFactory());
		$this->expectException(SqlAnnotationException::class);
		$reader->getStateMachineDefinition(new \ReflectionClass($className));
	}


	public function conflictingAnnotationClassesProvider()
	{
		yield ConflictingAnnotations::class => [ConflictingAnnotations::class];
		yield ConflictingAnnotations2::class => [ConflictingAnnotations2::class];
		yield ConflictingAnnotationsWithId::class => [ConflictingAnnotationsWithId::class];
		yield ConflictingAnnotationsWithId2::class => [ConflictingAnnotationsWithId2::class];
	}


	public function testUseReferenceTrait()
	{
		$reader = new AnnotationReader(StateMachineDefinitionBuilderFactory::createDefaultFactory());
		$this->expectException(\InvalidArgumentException::class);
		$reader->getStateMachineDefinition(new \ReflectionClass(UseReferenceTrait::class));
	}


	public function testMultipleStateAnnotations()
	{
		$reader = new AnnotationReader(StateMachineDefinitionBuilderFactory::createDefaultFactory());
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage("Multiple @State annotations at EXISTS constant.");
		$reader->getStateMachineDefinition(new \ReflectionClass(MultipleStateAnnotations::class));
	}


	public function testMultipleConstants()
	{
		$reader = new AnnotationReader(StateMachineDefinitionBuilderFactory::createDefaultFactory());
		$definition = $reader->getStateMachineDefinition(new \ReflectionClass(MultipleConstants::class));

		$states = $definition->getStates();
		$this->assertCount(2, $states);
	}


	public function testTransitionWithoutDefinition()
	{
		ApplyToEverything::resetCounters();

		$reader = new AnnotationReader(StateMachineDefinitionBuilderFactory::createDefaultFactory());
		$definition = $reader->getStateMachineDefinition(new \ReflectionClass(TransitionWithoutDefinition::class));

		$actions = $definition->getActions();
		$this->assertCount(1, $actions);

		$transitions = $definition->getTransitions();
		$this->assertCount(0, $transitions);

		$this->assertEquals(1, ApplyToEverything::$calledMethodCounter[ApplyToEverything::class . '::applyToPlaceholder']);
		$this->assertEquals(1, ApplyToEverything::$calledMethodCounter[ApplyToEverything::class . '::applyToActionPlaceholder']);
		ApplyToEverything::resetCounters();
	}


	public function testTransitionWithoutState()
	{
		$reader = new AnnotationReader(StateMachineDefinitionBuilderFactory::createDefaultFactory());
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage("Transition annotation requires none or two arguments - a source state and a list of target states.");
		$reader->getStateMachineDefinition(new \ReflectionClass(TransitionWithoutStates::class));
	}


	public function testAnnotationInterfaceCalls()
	{
		ApplyToEverything::resetCounters();

		$reader = new AnnotationReader(StateMachineDefinitionBuilderFactory::createDefaultFactory());
		$reader->getStateMachineDefinition(new \ReflectionClass(ApplyToMachineDefinition::class));

		$this->assertNotEmpty(ApplyToEverything::$calledMethods);

		foreach (ApplyToEverything::$calledMethodCounter as $method => $count) {
			switch($method) {
				case ApplyToEverything::class . '::applyToPlaceholder':
					$this->assertEquals(4, $count, $method);
					break;
				case ApplyToEverything::class . '::applyToActionPlaceholder':
					$this->assertEquals(0, $count, $method);
					break;
				default:
					$this->assertEquals(1, $count, $method);
					break;
			}
		}

		ApplyToEverything::resetCounters();
	}


	public function testPropertyDefinition()
	{
		$reader = new AnnotationReader(StateMachineDefinitionBuilderFactory::createDefaultFactory());
		$definition = $reader->getStateMachineDefinition(new \ReflectionClass(PropertyDefinition::class));
		$properties = $definition->getProperties();
		$this->assertCount(2, $properties);

		$idProperty = $definition->getProperty('id');
		$this->assertEquals('int', $idProperty->getType());
		$this->assertFalse($idProperty->isNullable());

		$fooProperty = $definition->getProperty('foo');
		$this->assertEquals('string', $fooProperty->getType());
		$this->assertTrue($fooProperty->isNullable());
	}


	public function testPropertyGetterDefinition()
	{
		$reader = new AnnotationReader(StateMachineDefinitionBuilderFactory::createDefaultFactory());
		$definition = $reader->getStateMachineDefinition(new \ReflectionClass(PropertyGetterDefinition::class));
		$properties = $definition->getProperties();
		$this->assertCount(2, $properties);

		$idProperty = $definition->getProperty('id');
		$this->assertEquals('string', $idProperty->getType());
		$this->assertFalse($idProperty->isNullable());

		$fooProperty = $definition->getProperty('foo');
		$this->assertEquals('string', $fooProperty->getType());
		$this->assertFalse($fooProperty->isNullable());
	}


	public function testCanonizeFileName()
	{
		$a = new class extends AbstractIncludeAnnotation {
			public ?string $baseDirName = null;
			public function canonizeFileName(?string $fileName): ?string
			{
				return parent::canonizeFileName($fileName);
			}
		};

		$this->assertNull($a->canonizeFileName(null));
		$this->assertEquals('foo', $a->canonizeFileName('foo'));
		$this->assertEquals('/foo', $a->canonizeFileName('/foo'));
		$this->assertEquals('foo/bar', $a->canonizeFileName('foo/bar'));

		$a->baseDirName = __DIR__;

		$this->assertNull($a->canonizeFileName(null));
		$this->assertEquals('test/foo', $a->canonizeFileName('foo'));
		$this->assertEquals('/foo', $a->canonizeFileName('/foo'));
		$this->assertEquals('test/foo/bar', $a->canonizeFileName('foo/bar'));
	}

}

