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

use Smalldb\Graph\Grafovatko\GrafovatkoExporter;
use Smalldb\Graph\Grafovatko\Processor;
use Smalldb\Graph\Graph;
use Smalldb\StateMachine\Test\TestTemplate\TestOutput;


class GrafovatkoTest extends TestCase
{

	/**
	 * Build a balanced binary tree
	 *
	 *        ___A___
	 *      _B_     _C_
	 *     D   E   F   G
	 */
	private function buildBalancedBinaryTree(): Graph
	{
		$graph = new Graph();

		$a = $graph->createNode('A');
		$b = $graph->createNode('B');
		$c = $graph->createNode('C');
		$d = $graph->createNode('D');
		$e = $graph->createNode('E');
		$f = $graph->createNode('F');
		$g = $graph->createNode('G');

		$graph->createEdge(null, $a, $b);
		$graph->createEdge(null, $a, $c);
		$graph->createEdge(null, $b, $d);
		$graph->createEdge(null, $b, $e);
		$graph->createEdge(null, $c, $f);
		$graph->createEdge(null, $c, $g);

		$aGraph = $a->getNestedGraph();
		$aGraph->createEdge(null, $aGraph->createNode('AA'), $aGraph->createNode('BB'));

		return $graph;
	}


	public function testGraphDump()
	{
		$graph = $this->buildBalancedBinaryTree();
		$exporter = new GrafovatkoExporter($graph);

		ob_start();
		$exporter->dumpNodeTree();
		$output = ob_get_clean();
		$outputLines = explode("\n", $output);

		foreach ($graph->getAllNodes() as $id => $n) {
			$this->assertNotEmpty(preg_grep('/^\s*\* ' . preg_quote($n->getId(), '/') . '$/', $outputLines),
				"Node " . $n->getId() . " not found in the output:\n" . $output);
		}

		foreach ($graph->getAllEdges() as $id => $e) {
			$this->assertNotEmpty(preg_grep('/^\s*- ' . preg_quote($e->getId(), '/') . ' (.*)$/', $outputLines),
				"Edge " . $e->getId() . " not found in the output:\n" . $output);
		}
	}


	public function testGraphExport()
	{
		$graph = $this->buildBalancedBinaryTree();
		$exporter = new GrafovatkoExporter($graph);
		$exporter->addProcessor(new Processor());
		$jsonArray = $exporter->export();

		$this->assertEquals(count($graph->getNodes()), count($jsonArray['nodes']));
		$this->assertEquals(count($graph->getEdges()), count($jsonArray['edges']));
	}


	public function testGraphExportJsonString()
	{
		$graph = $this->buildBalancedBinaryTree();
		$exporter = new GrafovatkoExporter($graph);

		$jsonString = $exporter->exportJsonString();
		$jsonArray = json_decode($jsonString, true);

		$this->assertEquals(count($graph->getNodes()), count($jsonArray['nodes']));
		$this->assertEquals(count($graph->getEdges()), count($jsonArray['edges']));
	}


	public function testGraphExportJsonStringWithPrefix()
	{
		$graph = $this->buildBalancedBinaryTree();
		$exporter = new GrafovatkoExporter($graph);
		$prefix = '_foo_';
		$exporter->setPrefix($prefix);
		$this->assertEquals($prefix, $exporter->getPrefix());

		$jsonString = $exporter->exportJsonString();

		foreach ($graph->getAllNodes() as $id => $n) {
			$this->assertStringContainsString('"id":"' . $prefix . $n->getId() . '"', $jsonString);
		}

		foreach ($graph->getAllEdges() as $id => $e) {
			$this->assertStringContainsString('"id":"' . $prefix . $e->getId() . '"', $jsonString);
		}
	}


	public function testGraphExportSvgElement()
	{
		$graph = $this->buildBalancedBinaryTree();
		$exporter = new GrafovatkoExporter($graph);

		$svgElement = $exporter->exportSvgElement(['class' => 'foo-class']);

		$dom = new \DOMDocument();
		$dom->loadXML($svgElement);
		/** @var \DOMElement[] $svgElements */
		$svgElements = $dom->getElementsByTagName('svg');
		$this->assertCount(1, $svgElements);
		$svgElement = $svgElements[0];
		$jsonString = $svgElement->getAttribute('data-graph');
		$this->assertEquals('foo-class', $svgElement->getAttribute('class'));

		foreach ($graph->getAllNodes() as $id => $n) {
			$this->assertStringContainsString('"id":"' . $n->getId() . '"', $jsonString);
		}

		foreach ($graph->getAllEdges() as $id => $e) {
			$this->assertStringContainsString('"id":"' . $e->getId() . '"', $jsonString);
		}

	}


	public function testGraphExportHtml()
	{
		$output = new TestOutput();
		$outputFile = $output->outputPath('html-graph.html');

		$graph = $this->buildBalancedBinaryTree();
		$exporter = new GrafovatkoExporter($graph);

		$exporter->exportHtmlFile($outputFile);

		$dom = new \DOMDocument();
		$dom->load($outputFile);

		$svgElements = $dom->getElementsByTagName('svg');
		$this->assertCount(1, $svgElements);
	}

}
