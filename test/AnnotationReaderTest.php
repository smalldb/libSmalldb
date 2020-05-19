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
use Smalldb\StateMachine\Definition\AnnotationReader\AnnotationReader;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilderFactory;
use Smalldb\StateMachine\Definition\StateDefinition;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Definition\TransitionDefinition;
use Smalldb\StateMachine\SqlExtension\AnnotationException as SqlAnnotationException;
use Smalldb\StateMachine\Test\BadExample\ConflictingAnnotations;
use Smalldb\StateMachine\Test\BadExample\ConflictingAnnotations2;
use Smalldb\StateMachine\Test\BadExample\ConflictingAnnotationsWithId;
use Smalldb\StateMachine\Test\BadExample\ConflictingAnnotationsWithId2;
use Smalldb\StateMachine\Test\BadExample\UseReferenceTrait;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItem;


class AnnotationReaderTest extends TestCase
{

	public function testCrudItem()
	{
		$reader = new AnnotationReader(StateMachineDefinitionBuilderFactory::createDefaultFactory());
		$definition = $reader->getStateMachineDefinition(CrudItem::class);
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
		$reader->getStateMachineDefinition($className);
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
		$reader->getStateMachineDefinition(UseReferenceTrait::class);
	}

}

