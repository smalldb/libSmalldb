<?php declare(strict_types = 1);
/*
 * Copyright (c) 2018, Josef Kufner  <josef@kufner.cz>
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


namespace Smalldb\StateMachine\Graph\Grafovatko;

use Smalldb\StateMachine\Graph\Edge;
use Smalldb\StateMachine\Graph\Graph;
use Smalldb\StateMachine\Graph\NestedGraph;
use Smalldb\StateMachine\Graph\Node;


class GrafovatkoExporter
{
	/** @var ProcessorInterface[] */
	private $processors = [];

	/**
	 * Global graph IDs prefix
	 *
	 * @var string
	 */
	private $prefix = '';


	public function __construct()
	{
	}


	public function addProcessor(ProcessorInterface $processor): self
	{
		$this->processors[] = $processor;
		return $this;
	}


	public function export(Graph $graph): array
	{
		return $this->exportNestedGraph($graph);
	}


	public function getPrefix(): string
	{
		return $this->prefix;
	}


	public function setPrefix(string $prefix): self
	{
		$this->prefix = $prefix;
		return $this;
	}


	protected function processGraph(NestedGraph $graph, array $exportedGraph): array
	{
		foreach ($this->processors as $processor) {
			$exportedGraph = $processor->processGraph($graph, $exportedGraph);
		}
		return $exportedGraph;
	}


	protected function processNodeAttrs(Node $node, array $exportedNode): array
	{
		foreach ($this->processors as $processor) {
			$exportedNode = $processor->processNodeAttrs($node, $exportedNode);
		}
		return $exportedNode;
	}


	protected function processEdgeAttrs(Edge $edge, array $exportedEdge): array
	{
		foreach ($this->processors as $processor) {
			$exportedEdge = $processor->processEdgeAttrs($edge, $exportedEdge);
		}
		return $exportedEdge;
	}


	/**
	 * Debug: Dump plain text representation of the graph hierarchy
	 */
	public function dumpNodeTree(NestedGraph $graph, $indent = "", $withEdges = true)
	{
		if ($withEdges) {
			foreach ($graph->getEdges() as $edge) {
				echo $indent, "- ", $edge->getId(), ' (', $edge->getStart()->getId(), ' -> ', $edge->getEnd()->getId(), ")\n";
			}
		}

		foreach ($graph->getNodes() as $node) {
			echo $indent, "* ", $node->getId(), "\n";
			if ($node->hasNestedGraph()) {
				$this->dumpNodeTree($node->getNestedGraph(), $indent . "\t");
			}
		}
	}

	/**
	 * Export $graph to JSON array.
	 */
	private function exportNestedGraph(NestedGraph $graph): array
	{
		$nodes = [];
		foreach ($graph->getNodes() as $node) {
			$nodeJson = [
				'id' => $this->prefix . $node->getId(),
				'graph' => $node->hasNestedGraph() ? $this->exportNestedGraph($node->getNestedGraph()) : null,
				'attrs' => $this->processNodeAttrs($node, $node->getAttributes()),
			];
			if ($nodeJson !== null) {
				$nodes[] = $nodeJson;
			}
		}

		$edges = [];
		foreach ($graph->getEdges() as $edge) {
			$edgeJson = [
				'id' => $this->prefix . $edge->getId(),
				'start' => $this->prefix . $edge->getStart()->getId(),
				'end' => $this->prefix . $edge->getEnd()->getId(),
				'attrs' => $this->processEdgeAttrs($edge, $edge->getAttributes()),
			];
			if ($edgeJson !== null) {
				$edges[] = $edgeJson;
			}
		}

		return $this->processGraph($graph, [
			'layout' => 'dagre',
			'nodes' => $nodes,
			'edges' => $edges,
		]);
	}

}
