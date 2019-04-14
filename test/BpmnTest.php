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
use Smalldb\StateMachine\BpmnGrafovatkoProcessor;
use Smalldb\StateMachine\BpmnReader;
use Smalldb\StateMachine\BpmnSvgPainter;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilder;
use Smalldb\StateMachine\Definition\StateDefinition;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Graph\Edge;
use Smalldb\StateMachine\Graph\Grafovatko\GrafovatkoExporter;
use Smalldb\StateMachine\Graph\Graph;
use Smalldb\StateMachine\Graph\NestedGraph;
use Smalldb\StateMachine\Graph\Node;
use Smalldb\StateMachine\Test\Example\TestTemplate\Html;
use Smalldb\StateMachine\Test\Example\TestTemplate\TestOutputTemplate;


class BpmnTest extends TestCase
{
	private $outputDir;

	public function setUp(): void
	{
		$this->outputDir = __DIR__ . '/output';
		if (!is_dir($this->outputDir)) {
			$outputDirCreated = mkdir($this->outputDir);
			$this->assertTrue($outputDirCreated, 'Failed to create output directory: ' . $this->outputDir);
		}
	}


	public function testBasicExport()
	{
		// Build a simple CRUD machine
		$builder = new StateMachineDefinitionBuilder();
		$builder->setMachineType('crud-item');
		$builder->addState('Exists');
		$builder->addTransition('create', '', ['Exists']);
		$builder->addTransition('update', 'Exists', ['Exists']);
		$builder->addTransition('delete', 'Exists', ['']);
		$definition = $builder->build();
		$this->assertInstanceOf(StateMachineDefinition::class, $definition);

		// Render the result
		$output = new TestOutputTemplate();
		$output->setTitle('CRUD Item');
		$output->addStateMachineGraph($definition);
		$output->writeHtmlFile('index.html');
	}


	/**
	 * @dataProvider bpmnFileProvider
	 */
	public function testBpmnDiagram(string $bpmnFilename, ?string $svgFilename, bool $expectErrors = false)
	{
		$this->assertFileExists($bpmnFilename);
		$machineType = preg_replace('/\.[^.]*$/', '', basename($bpmnFilename));

		// Read BPMN diagram
		$bpmnReader = BpmnReader::readBpmnFile($bpmnFilename);
		$definitionBuilder = $bpmnReader->inferStateMachine("Participant_StateMachine");
		$definitionBuilder->setMachineType($machineType);

		// Infer the state machine
		$definition = $definitionBuilder->build();
		if ($expectErrors) {
			$this->assertTrue($definition->hasErrors(), 'There should be some errors in the BPMN diagram.');
		} else {
			$this->assertNotTrue($definition->hasErrors(), 'There are unexpected errors in the BPMN diagram.');
		}

		// Assert the definition has a few reachable states
		$reachableStates = $definition->findReachableStates();
		$this->assertContainsOnlyInstancesOf(StateDefinition::class, $reachableStates);
		$this->assertGreaterThanOrEqual(2, count($reachableStates), 'Failed to find at least two reachable states.');

		$this->writeBpmnPage($bpmnFilename . '.html', $definition, $bpmnReader->getBpmnGraph(), basename($bpmnFilename), $svgFilename);
	}

	private function createBpmnUserNode(NestedGraph $userGraph, string $id, string $type, ?string $name = null, string $process = 'Process_User'): Node
	{
		return $userGraph->createNode($id, [
			'id' => $id,
			'name' => $name ?? $id,
			'type' => $type,
			'process' => $process,
			'features' => [],
			'_generated' => false,
		]);
	}

	/**
	 * @param string $type
	 * @param Node $sourceNode
	 * @param Node $targetNode
	 * @param string|null $name
	 * @return Edge
	 */
	private function createEdge(string $type, Node $sourceNode, Node $targetNode, ?string $name = null): Edge
	{
		// Find appropriate graph where we should place the edge
		/** @var NestedGraph $edgeGraph */
		$sourceGraph = $sourceNode->getGraph();
		$targetGraph = $targetNode->getGraph();
		$edgeGraph = ($sourceGraph === $targetGraph ? $sourceGraph : $sourceNode->getRootGraph());

		// Create arrow
		$id = $type . count($edgeGraph->getEdges());
		return $edgeGraph->createEdge($id, $sourceNode, $targetNode, [
			'id' => $id,
			'type' => $type,
			'name' => $name,
		]);
	}

	/**
	 * @param string|Node $sourceNode
	 * @param string|Node $targetNode
	 * @param string|null $name
	 * @return Edge
	 */
	private function createSequenceFlow(Node $sourceNode, Node $targetNode): Edge
	{
		return $this->createEdge('sequenceFlow', $sourceNode, $targetNode);
	}


	/**
	 * @param string|Node $sourceNode
	 * @param string|null $name
	 * @return Edge
	 */
	private function createMessageFlow(Node $sourceNode, Node $targetNode, string $transitionName): Edge
	{
		return $this->createEdge('messageFlow', $sourceNode, $targetNode, $transitionName);
	}


	private function generateNoodleBpmn(int $taskCount = 7): Graph
	{
		$bpmnGraph = new Graph();

		$userParticipant = $bpmnGraph->createNode('Participant_User', [
			'id' => 'Participant_User',
			'name' => 'User',
			'type' => 'participant',
			'process' => 'Process_User',
			'features' => [],
			'_generated' => false,
		]);

		$stateMachineParticipant = $bpmnGraph->createNode('Participant_StateMachine', [
			'id' => 'Participant_StateMachine',
			'name' => 'State Machine',
			'type' => 'participant',
			'process' => 'Process_StateMachine',
			'features' => [],
			'_generated' => false,
		]);

		$userGraph = $userParticipant->getNestedGraph();
		$startEvent = $this->createBpmnUserNode($userGraph, 'start', 'startEvent');
		$endEvent = $this->createBpmnUserNode($userGraph, 'end', 'endEvent');

		// Create a simple tasks
		$prevNode = $startEvent;
		for ($t = 0; $t < $taskCount; $t++) {
			$taskNode = $this->createBpmnUserNode($userGraph, 'Task'.$t, 'task');
			$this->createSequenceFlow($prevNode, $taskNode);
			$this->createMessageFlow($taskNode, $stateMachineParticipant, 't' . $t);
			$prevNode = $taskNode;
		}
		$this->createSequenceFlow($prevNode, $endEvent);

		$this->assertCount($taskCount + 4, $bpmnGraph->getAllNodes(), 'Unexpected node count.');
		$this->assertCount(2 * $taskCount + 1, $bpmnGraph->getAllEdges(), 'Unexpected edge count.');

		return $bpmnGraph;
	}

	public function testGeneratedBpmnDiagram()
	{
		$bpmnGraph = $this->generateNoodleBpmn(7);
		$bpmnReader = BpmnReader::readGraph($bpmnGraph);
		$definitionBuilder = $bpmnReader->inferStateMachine("Participant_StateMachine");
		$definitionBuilder->setMachineType('noodle');
		$definition = $definitionBuilder->build();
		$this->writeBpmnPage('noodle.html', $definition, $bpmnReader->getBpmnGraph(), 'Generated Noodle', null, true);
	}

	private function writeBpmnPage(string $outputFilename, StateMachineDefinition $definition, Graph $bpmnGraph, string $title, ?string $svgFilename = null, bool $horizontalLayout = false)
	{
		// Render the infered state machine
		$output = new TestOutputTemplate();
		$output->setTitle($title);
		$output->addStateMachineGraph($definition, $horizontalLayout);
		$output->addHtml(Html::hr());

		// Render BPMN diagram using the SVG image
		if ($svgFilename) {
			$this->assertFileExists($svgFilename);
			$svgContent = file_get_contents($svgFilename);
			$svgPainter = new BpmnSvgPainter();
			$colorizedSvgContent = $svgPainter->colorizeSvgFile($svgContent, $bpmnGraph, [], '');

			$svgUrl = $output->writeResource($svgFilename, $colorizedSvgContent);
			$output->addHtml(Html::img(['src' => $svgUrl]));
			$output->addHtml(Html::hr());
		}

		// Render BPMN diagram using Grafovatko
		$renderer = new GrafovatkoExporter();
		$renderer->addProcessor(new BpmnGrafovatkoProcessor());
		$output->addGrafovatko();
		$output->addHtml($renderer->exportSvgElement($bpmnGraph, ['class' => 'graph']));

		$output->writeHtmlFile($outputFilename);
	}


	public function bpmnFileProvider()
	{
		$filesPattern = __DIR__ . '/Example/Bpmn/*.bpmn';
		foreach (glob($filesPattern) as $bpmnFilename) {
			$basename = str_replace('.bpmn', '', basename($bpmnFilename));
			$svgFilename = dirname($bpmnFilename) . '/' . $basename . '.svg';
			yield $basename => [
				$bpmnFilename,
				file_exists($svgFilename) ? $svgFilename : null,
				false // expect no errors
			];
		}
	}

}
