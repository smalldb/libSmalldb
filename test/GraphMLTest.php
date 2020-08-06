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

use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilderFactory;
use Smalldb\StateMachine\GraphMLExtension\GraphMLException;
use Smalldb\StateMachine\GraphMLExtension\GraphMLReader;
use Smalldb\StateMachine\StyleExtension\Definition\StyleExtension;


class GraphMLTest extends TestCase
{

	public function testGraphMLReader()
	{
		$builder = StateMachineDefinitionBuilderFactory::createDefaultFactory()->createDefinitionBuilder();
		$builder->setMachineType('Foo');
		$reader = new GraphMLReader($builder);
		$reader->parseGraphMLFile(__DIR__ . '/Example/SupervisorProcess/SupervisorProcess.graphml');
		$stateMachineDefinition = $builder->build();
		$this->assertEmpty($stateMachineDefinition->getErrors());
		$this->assertCount(8, $stateMachineDefinition->getStates());
		$this->assertCount(12, $stateMachineDefinition->getTransitions());
	}


	public function testMissingLabel()
	{
		$builder = StateMachineDefinitionBuilderFactory::createDefaultFactory()->createDefinitionBuilder();
		$builder->setMachineType('Foo');
		$reader = new GraphMLReader($builder);
		$this->expectException(GraphMLException::class);
		$this->expectExceptionMessageMatches('/^Missing edge label/');
		$reader->parseGraphMLFile(__DIR__ . '/BadExample/GraphML/MissingLabel.graphml');
	}


	public function testEmptyLabel()
	{
		$builder = StateMachineDefinitionBuilderFactory::createDefaultFactory()->createDefinitionBuilder();
		$builder->setMachineType('Foo');
		$reader = new GraphMLReader($builder);
		$this->expectException(GraphMLException::class);
		$this->expectExceptionMessageMatches('/^Empty edge label/');
		$reader->parseGraphMLFile(__DIR__ . '/BadExample/GraphML/EmptyLabel.graphml');
	}


	public function testNestedGraph()
	{
		$builder = StateMachineDefinitionBuilderFactory::createDefaultFactory()->createDefinitionBuilder();
		$builder->setMachineType('Foo');
		$reader = new GraphMLReader($builder);
		$reader->parseGraphMLFile(__DIR__ . '/BadExample/GraphML/NestedCreate.graphml', 'Nested Graph');
		$stateMachineDefinition = $builder->build();
		$this->assertEmpty($stateMachineDefinition->getErrors());
		$this->assertCount(2, $stateMachineDefinition->getStates());
		$this->assertCount(1, $stateMachineDefinition->getTransitions());
	}


	public function testNestedNonexistentGraph()
	{
		$builder = StateMachineDefinitionBuilderFactory::createDefaultFactory()->createDefinitionBuilder();
		$builder->setMachineType('Foo');
		$reader = new GraphMLReader($builder);
		$this->expectException(GraphMLException::class);
		$this->expectExceptionMessageMatches('/^Graph node not found:/');
		$reader->parseGraphMLFile(__DIR__ . '/BadExample/GraphML/NestedCreate.graphml', 'Nonexistent Graph');
	}


	public function testColor()
	{
		$builder = StateMachineDefinitionBuilderFactory::createDefaultFactory()->createDefinitionBuilder();
		$builder->setMachineType('Foo');
		$reader = new GraphMLReader($builder);
		$reader->parseGraphMLFile(__DIR__ . '/BadExample/GraphML/WithProperties.graphml');
		$stateMachineDefinition = $builder->build();
		$this->assertEmpty($stateMachineDefinition->getErrors());

		$sExists = $stateMachineDefinition->getState('Exists');
		$this->assertTrue($sExists->hasExtension(StyleExtension::class));
		$this->assertEqualsIgnoringCase("#eeffcc", $sExists->getExtension(StyleExtension::class)->getColor());

		$tCreate = $stateMachineDefinition->getTransition('create', '');
		$this->assertTrue($tCreate->hasExtension(StyleExtension::class));
		$this->assertEqualsIgnoringCase("#44aa00", $tCreate->getExtension(StyleExtension::class)->getColor());
	}


	public function testWithProperties()
	{
		$builder = StateMachineDefinitionBuilderFactory::createDefaultFactory()->createDefinitionBuilder();
		$builder->setMachineType('Foo');
		$reader = new GraphMLReader($builder);
		$reader->parseGraphMLFile(__DIR__ . '/BadExample/GraphML/WithProperties.graphml');
		$stateMachineDefinition = $builder->build();
		$this->assertEmpty($stateMachineDefinition->getErrors());
		// TODO: We should handle the properties somehow.
	}


	public function testNestedWithProperties()
	{
		$builder = StateMachineDefinitionBuilderFactory::createDefaultFactory()->createDefinitionBuilder();
		$builder->setMachineType('Foo');
		$reader = new GraphMLReader($builder);
		$reader->parseGraphMLFile(__DIR__ . '/BadExample/GraphML/NestedWithProperties.graphml', 'Nested Graph');
		$stateMachineDefinition = $builder->build();
		$this->assertEmpty($stateMachineDefinition->getErrors());
		// TODO: We should handle the properties somehow.
	}


	public function testProperties()
	{
		$builder = StateMachineDefinitionBuilderFactory::createDefaultFactory()->createDefinitionBuilder();
		$builder->setMachineType('Foo');
		$reader = new GraphMLReader($builder);
		$reader->parseGraphMLFile(__DIR__ . '/BadExample/GraphML/Properties.graphml');
		$stateMachineDefinition = $builder->build();
		$this->assertEmpty($stateMachineDefinition->getErrors());

		// TODO: We should handle the properties somehow.
		$graphAttrs = $reader->getGraph()->getAttributes();
		$this->assertNotEmpty($graphAttrs['properties']);
		$this->assertEqualsIgnoringCase('foo', $graphAttrs['properties']['foo']['name']);
		$this->assertEqualsIgnoringCase('bar', $graphAttrs['properties']['bar']['name']);
	}

}
