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

		$this->createBpmnPage($definition, $bpmnReader->getBpmnGraph(), basename($bpmnFilename), $svgFilename)
			->writeHtmlFile($bpmnFilename . '.html');
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

	private function generateUserDecidesBpmn(int $taskCount = 7): Graph
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

		// Create
		$createTask = $this->createBpmnUserNode($userGraph, 'Create', 'task', 'Create issue');
		$this->createSequenceFlow($startEvent, $createTask);
		$this->createMessageFlow($createTask, $stateMachineParticipant, 'create');

		// Do something
		$processTask = $this->createBpmnUserNode($userGraph, 'Process', 'task', 'Process issue');
		$this->createSequenceFlow($createTask, $processTask);

		// Gateway
		$gateway = $this->createBpmnUserNode($userGraph, 'GW', 'exclusiveGateway');
		$this->createSequenceFlow($processTask, $gateway);

		for ($n = 0; $n < $taskCount; $n++) {
			// Store result
			$resultTaskNode = $this->createBpmnUserNode($userGraph, 'ResultTask' . $n, 'task', 'Store result ' . $n);
			$this->createSequenceFlow($gateway, $resultTaskNode);
			$this->createMessageFlow($resultTaskNode, $stateMachineParticipant, 'storeResult'.$n);

			// End
			$endEvent = $this->createBpmnUserNode($userGraph, 'end'.$n, 'endEvent');
			$this->createSequenceFlow($resultTaskNode, $endEvent);
		}

		$this->assertCount(2 * $taskCount + 6, $bpmnGraph->getAllNodes(), 'Unexpected node count.');
		$this->assertCount(3 * $taskCount + 4, $bpmnGraph->getAllEdges(), 'Unexpected edge count.');

		return $bpmnGraph;
	}


	private function generateMachineDecidesBpmn(int $taskCount = 7): Graph
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

		// Create
		$createTask = $this->createBpmnUserNode($userGraph, 'Create', 'task', 'Create issue');
		$this->createSequenceFlow($startEvent, $createTask);
		$this->createMessageFlow($createTask, $stateMachineParticipant, 'create');

		// Do something
		$processTask = $this->createBpmnUserNode($userGraph, 'Process', 'task', 'Process issue');
		$this->createSequenceFlow($createTask, $processTask);

		// Store result
		$storeResultTask = $this->createBpmnUserNode($userGraph, 'ResultTask', 'task', 'Store result');
		$this->createSequenceFlow($processTask, $storeResultTask);
		$this->createMessageFlow($storeResultTask, $stateMachineParticipant, 'storeResult');

		// Gateway
		$gateway = $this->createBpmnUserNode($userGraph, 'GW', 'eventBasedGateway');
		$this->createSequenceFlow($storeResultTask, $gateway);

		for ($n = 0; $n < $taskCount; $n++) {
			// Receive result
			$resultReceiveNode = $this->createBpmnUserNode($userGraph, 'ReceiveResultTask' . $n, 'intermediateCatchEvent');
			$this->createSequenceFlow($gateway, $resultReceiveNode);
			$this->createMessageFlow($stateMachineParticipant, $resultReceiveNode, 'result'.$n);

			// Process result
			$resultProcessTask = $this->createBpmnUserNode($userGraph, 'ProcessResultTask' . $n, 'task', '@Result' . $n);
			$this->createSequenceFlow($resultReceiveNode, $resultProcessTask);

			// End
			$endEvent = $this->createBpmnUserNode($userGraph, 'end'.$n, 'endEvent');
			$this->createSequenceFlow($resultProcessTask, $endEvent);
		}

		$this->assertCount(3 * $taskCount + 7, $bpmnGraph->getAllNodes(), 'Unexpected node count.');
		$this->assertCount(4 * $taskCount + 6, $bpmnGraph->getAllEdges(), 'Unexpected edge count.');

		return $bpmnGraph;
	}

	private function generateBothDecideBpmn(int $taskCount = 9): Graph
	{
		$realTaskCount = (int) sqrt($taskCount);

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

		// Create
		$createTask = $this->createBpmnUserNode($userGraph, 'Create', 'task', 'Create issue');
		$this->createSequenceFlow($startEvent, $createTask);
		$this->createMessageFlow($createTask, $stateMachineParticipant, 'create');

		// Gateway
		$gateway = $this->createBpmnUserNode($userGraph, 'GW', 'exclusiveGateway');
		$this->createSequenceFlow($createTask, $gateway);

		$processTasks = [];

		for ($n = 0; $n < $realTaskCount; $n++) {
			// Process Issue
			$processTask = $processTasks[$n] = $this->createBpmnUserNode($userGraph, 'ProcessIssute' . $n, 'task', 'Process Issue ' . $n);
			$this->createSequenceFlow($gateway, $processTask);
			$this->createMessageFlow($processTask, $stateMachineParticipant, 'storeResult'.$n);
		}

		$gateways = [];

		for ($n = 0; $n < $realTaskCount; $n++) {
			// Store result
			$storeResultTask = $this->createBpmnUserNode($userGraph, 'ResultTask' . $n, 'task', 'Store result ' . $n);
			$this->createSequenceFlow($processTasks[$n], $storeResultTask);
			$this->createMessageFlow($storeResultTask, $stateMachineParticipant, 'storeResult' . $n);

			// Gateway
			$gateway = $gateways[$n] = $this->createBpmnUserNode($userGraph, 'GW'. $n, 'eventBasedGateway');
			$this->createSequenceFlow($storeResultTask, $gateway);
		}

		for ($n = 0; $n < $realTaskCount; $n++) {
			// Receive result
			$resultReceiveNode = $this->createBpmnUserNode($userGraph, 'ReceiveResultTask' . $n, 'intermediateCatchEvent');
			for ($ng = 0; $ng < $realTaskCount; $ng++) {
				$this->createSequenceFlow($gateways[$ng], $resultReceiveNode);
			}
			$this->createMessageFlow($stateMachineParticipant, $resultReceiveNode, 'result'.$n);

			// Process result
			$resultProcessTask = $this->createBpmnUserNode($userGraph, 'ProcessResultTask' . $n, 'task', 'Process result ' . $n);
			$this->createSequenceFlow($resultReceiveNode, $resultProcessTask);
			$this->createMessageFlow($resultProcessTask, $stateMachineParticipant, 'processResult'.$n);

			// End
			$endEvent = $this->createBpmnUserNode($userGraph, 'end'.$n, 'endEvent');
			$this->createSequenceFlow($resultProcessTask, $endEvent);
		}

		$this->assertCount(3 + 3 * $realTaskCount + 3 * $realTaskCount + 2, $bpmnGraph->getAllNodes(), 'Unexpected node count.');
		$this->assertCount(3 + 5 * $realTaskCount + $realTaskCount * ($realTaskCount + 1) + 3 * $realTaskCount, $bpmnGraph->getAllEdges(), 'Unexpected edge count.');

		return $bpmnGraph;
	}


	/**
	 * @dataProvider noodleTimeProvider
	 */
	public function testGeneratedBpmnNoodle(int $testRunId = 0, int $N = 0)
	{
		// Example of the generated graph
		$bpmnGraph = $this->generateNoodleBpmn(7);
		$this->runGeneratedTest($testRunId, $N, $bpmnGraph, 'noodle', 'Generated Noodle', true);
	}

	/**
	 * @dataProvider noodleTimeProvider
	 */
	public function testGeneratedBpmnUserDecides(int $testRunId = 0, int $N = 0)
	{
		// Example of the generated graph
		$bpmnGraph = $this->generateUserDecidesBpmn(7);
		$this->runGeneratedTest($testRunId, $N, $bpmnGraph, 'user-decides', 'Generated UserDecides', false);
	}


	/**
	 * @dataProvider noodleTimeProvider
	 */
	public function testGeneratedBpmnMachineDecides(int $testRunId = 0, int $N = 0)
	{
		// Example of the generated graph
		$bpmnGraph = $this->generateMachineDecidesBpmn(7);
		$this->runGeneratedTest($testRunId, $N, $bpmnGraph, 'machine-decides', 'Generated MachineDecides', false);
	}

	/**
	 * @dataProvider noodleTimeProvider
	 */
	public function testGeneratedBpmnBothDecide(int $testRunId = 0, int $N = 0)
	{
		// Example of the generated graph
		$bpmnGraph = $this->generateBothDecideBpmn(9);
		$this->runGeneratedTest($testRunId, $N, $bpmnGraph, 'mess', 'Generated Mess', false);
	}


	private function runGeneratedTest(int $testRunId, int $N,
		Graph $bpmnGraph, string $machineType, string $title, bool $horizontalLayout = false)
	{
		$bpmnReader = BpmnReader::readGraph($bpmnGraph);
		$definitionBuilder = $bpmnReader->inferStateMachine("Participant_StateMachine");
		$definitionBuilder->setMachineType($machineType);
		$definition = $definitionBuilder->build();

		$output = $this->createBpmnPage($definition, $bpmnReader->getBpmnGraph(), $title, null, $horizontalLayout);

		// Run the benchmark for $N
		if ($N > 0) {
			$bpmnGraph = $this->generateNoodleBpmn($N);
			$nEV = count($bpmnGraph->getAllNodes()) + count($bpmnGraph->getAllEdges());
			$this->runBenchmark($output, $machineType, $bpmnGraph, $testRunId, $nEV);
		} else {
			$testRunId = null;
			$curTimeLog = null;
		}

		// Print statistics
		$this->showTimeLogPlot($output, "$machineType-times.json", $testRunId);

		$output->writeHtmlFile("$machineType.html");
	}

	private function runBenchmark(TestOutputTemplate $output, string $machineType, Graph $bpmnGraph, $testRunId, int $N)
	{
		$bpmnReader = BpmnReader::readGraph($bpmnGraph);

		gc_collect_cycles();
		$tStart = getrusage();

		$bpmnReader->enableTimeLog();
		$bpmnReader->inferStateMachine("Participant_StateMachine");

		$tEnd = getrusage();
		$t_sec = ($tEnd['ru_utime.tv_sec'] + $tEnd['ru_utime.tv_usec'] / 1e6)
			- ($tStart['ru_utime.tv_sec'] + $tStart['ru_utime.tv_usec'] / 1e6);
		$timeLog = $bpmnReader->getTimeLog();

		$this->storeBenchmarkResult($output, "$machineType-times.json", [
			'id' => $testRunId,
			'N' => $N, 't_sec' => $t_sec,
			'mem_B' => memory_get_usage(false),
			'log' => $timeLog]);
	}

	private function storeBenchmarkResult(TestOutputTemplate $output, string $filename, array $results)
	{
		$filename = $output->outputPath($filename);
		$data = json_encode($results, JSON_NUMERIC_CHECK) . ",\n";
		if (file_put_contents($filename, $data, FILE_APPEND | LOCK_EX) === false) {
			throw new \RuntimeException('Failed to store results: ' . $filename);
		}
	}

	private function loadBenchmarkResults(TestOutputTemplate $output, $logFilename): array
	{
		$filename = $output->outputPath($logFilename);
		if (file_exists($filename)) {
			$data = file_get_contents($filename);
			$results = json_decode('[' . trim($data, ",\n") . ']', true);
			return $results;
		} else {
			return [];
		}
	}

	private function showTimeLogPlot(TestOutputTemplate $output, string $logFilename, $curTestRunId)
	{
		$output->addHtml(Html::hr());
		$output->addHtml(Html::h2([], 'Benchmark Results — Resource Usage'));
		$results = $this->loadBenchmarkResults($output, $logFilename);
		if (!$curTestRunId) {
			foreach ($results as $result) {
				$id = $result['id'];
				if ($id > $curTestRunId) {
					$curTestRunId = $id;
				}
			}
		}
		$datasets = [];
		foreach ($results as $result) {
			['id' => $id, 'N' => $N, 't_sec' => $t_sec, 'mem_B' => $mem_B] = $result;
			$datasets['T'.$id.':~']['data'][] = ['x' => $N, 'y' => $t_sec];
			$datasets['m'.$id.':~']['data'][] = ['x' => $N, 'y' => $mem_B/1048576];
			if (isset($result['log'])) {
				foreach ($result['log'] as $pos => $t) {
					if ($t > 0 && $t < $t_sec) {
						$datasets['T' . $id . ':' . $pos]['data'][] = ['x' => $N, 'y' => $t];
					}
				}
			}
		}
		foreach ($datasets as $id => & $dataset) {
			[$r, $pos] = explode(':', $id);
			if ($r === 'T'.$curTestRunId) {
				$dataset['borderColor'] = ($pos !== '~' ? '#96acd1' : '#5176be');
			} else if ($r === 'm'.$curTestRunId) {
				$dataset['borderColor'] = ($pos !== '~' ? '#79be92' : '#88be9b');
			} else {
				$dataset['borderColor'] = ($pos !== '~' ? '#eeeeee' : '#dddddd');
			}
			$dataset['yAxisID'] = ($r[0] == 'm' ? 'yMem' : 'yTime');
			$dataset['lineTension'] = 0;
			$dataset['fill'] = false;
			if (!empty($dataset['data'])) {
				sort($dataset['data']);
			}
		}
		unset($dataset);

		krsort($datasets);

		$output->addJs('https://cdnjs.cloudflare.com/ajax/libs/jquery/3.4.0/jquery.slim.min.js');
		$output->addJs('https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.bundle.min.js');
		// integrity="sha256-xKeoJ50pzbUGkpQxDYHD7o7hxe0LaOGeguUidbq6vis=" crossorigin="anonymous"
		$output->addHtml(Html::canvas(['id' => 'plot', 'class' => 'plot', 'data-set' => array_values($datasets)]));
		$output->addJs($output->resource('plot.js'));
	}

	private function createBpmnPage(StateMachineDefinition $definition, Graph $bpmnGraph,
		string $title, ?string $svgFilename = null, bool $horizontalLayout = false): TestOutputTemplate
	{
		// Render the infered state machine
		$output = new TestOutputTemplate();
		$output->setTitle($title);
		$output->addHtml(Html::h2([], 'State Machine'));
		$output->addStateMachineGraph($definition, $horizontalLayout);
		$output->addHtml(Html::hr());

		// Render BPMN diagram using the SVG image
		if ($svgFilename) {
			$this->assertFileExists($svgFilename);
			$svgContent = file_get_contents($svgFilename);
			$svgPainter = new BpmnSvgPainter();
			$colorizedSvgContent = $svgPainter->colorizeSvgFile($svgContent, $bpmnGraph, [], '');

			$svgUrl = $output->writeResource($svgFilename, $colorizedSvgContent);
			$output->addHtml(Html::h2([], 'Original BPMN Diagram (an SVG image with STS highlights)'));
			$output->addHtml(Html::img(['src' => $svgUrl]));
			$output->addHtml(Html::hr());
		}

		// Render BPMN diagram using Grafovatko
		$renderer = new GrafovatkoExporter();
		$renderer->addProcessor(new BpmnGrafovatkoProcessor());
		$output->addGrafovatko();
		$output->addHtml(Html::h2([], 'Graph of the BPMN Diagram'));
		$output->addHtml($renderer->exportSvgElement($bpmnGraph, ['class' => 'graph']));

		return $output;
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


	public function noodleTimeProvider()
	{
		$id = time();

		for ($N = 150; $N <= 300; $N += (int)($N / 2)) {
			yield "N = $N" => [$id, $N];
		}
	}

}
