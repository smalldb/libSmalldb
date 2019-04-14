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
use Smalldb\StateMachine\Graph\Grafovatko\GrafovatkoExporter;
use Smalldb\StateMachine\Graph\Graph;
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
		$output->setTitle($definition->getMachineType());
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

		$output = new TestOutputTemplate();
		$output->setTitle(basename($bpmnFilename));

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
		$output->addStateMachineGraph($definition);
		$output->addHtml(Html::hr());

		// Assert the definition has a few reachable states
		$reachableStates = $definition->findReachableStates();
		$this->assertContainsOnlyInstancesOf(StateDefinition::class, $reachableStates);
		$this->assertGreaterThanOrEqual(2, count($reachableStates), 'Failed to find at least two reachable states.');

		// Get BPMN graph
		$bpmnGraph = $bpmnReader->getBpmnGraph();
		$this->assertInstanceOf(Graph::class, $bpmnGraph);

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

		$output->writeHtmlFile(basename($bpmnFilename) . '.html');
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
