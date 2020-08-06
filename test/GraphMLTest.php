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

use Smalldb\Graph\Grafovatko\GrafovatkoExporter;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilderFactory;
use Smalldb\StateMachine\GraphMLExtension\GrafovatkoProcessor;
use Smalldb\StateMachine\GraphMLExtension\GraphMLException;
use Smalldb\StateMachine\GraphMLExtension\GraphMLExtension;
use Smalldb\StateMachine\GraphMLExtension\GraphMLReader;
use Smalldb\StateMachine\SmalldbDefinitionBagReader;
use Smalldb\StateMachine\StyleExtension\Definition\StyleExtension;
use Smalldb\StateMachine\Test\Example\SupervisorProcess\SupervisorProcess;


class GraphMLTest extends TestCase
{
	const SupervisorProcessGraphmlFile = __DIR__ . '/Example/SupervisorProcess/SupervisorProcess.graphml';

	public function testGraphMLReader()
	{
		$builder = StateMachineDefinitionBuilderFactory::createDefaultFactory()->createDefinitionBuilder();
		$builder->setMachineType('Foo');
		$reader = new GraphMLReader($builder);
		$reader->parseGraphMLFile(self::SupervisorProcessGraphmlFile);
		$stateMachineDefinition = $builder->build();
		$this->assertEmpty($stateMachineDefinition->getErrors());
		$this->assertCount(8, $stateMachineDefinition->getStates());
		$this->assertCount(13, $stateMachineDefinition->getTransitions());
	}


	public function testGraphMLDefinition()
	{

		$dbr = new SmalldbDefinitionBagReader();
		$dbr->addFromAnnotatedClass(SupervisorProcess::class);
		$db = $dbr->getDefinitionBag();
		$stateMachineDefinition = $db->getDefinition(SupervisorProcess::class);

		/** @var GraphMLExtension $graphmlExt */
		$graphmlExt = $stateMachineDefinition->getExtension(GraphMLExtension::class);
		[$diagramInfo] = $graphmlExt->getDiagramInfo();
		$this->assertEquals(realpath(self::SupervisorProcessGraphmlFile), realpath($diagramInfo->getGraphmlFileName()));
		$this->assertNull($diagramInfo->getGroup());
		$this->assertNotEmpty($diagramInfo->getGraph()->getAllNodes());
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


	public function testGraphProcessor()
	{
		$builder = StateMachineDefinitionBuilderFactory::createDefaultFactory()->createDefinitionBuilder();
		$builder->setMachineType('Foo');
		$reader = new GraphMLReader($builder);
		$reader->parseGraphMLFile(__DIR__ . '/Example/SupervisorProcess/SupervisorProcess.graphml');
		$graph = $reader->getGraph();

		$renderer = new GrafovatkoExporter($graph);
		$renderer->setPrefix('graphml_');
		$renderer->addProcessor(new GrafovatkoProcessor());
		$svg = $renderer->exportSvgElement();

		$dom = new \DOMDocument();
		$dom->loadXML($svg);
		$this->assertEquals('svg', $dom->documentElement->tagName);
		$jsonDataGraph = $dom->documentElement->getAttribute('data-graph');
		$this->assertJson($jsonDataGraph);

		$dataGraph = json_decode($jsonDataGraph, true);
		$nodeShapeCount = [];
		foreach ($dataGraph['nodes'] as $n) {
			$shape = $n['attrs']['shape'];
			$nodeShapeCount[$shape] = ($nodeShapeCount[$shape] ?? 0) + 1;
		}
		$this->assertEquals(1, $nodeShapeCount['uml.initial_state']);
		$this->assertGreaterThanOrEqual(5, $nodeShapeCount['uml.state']);

		$this->assertNotEmpty($dataGraph['edges']);
	}

}
