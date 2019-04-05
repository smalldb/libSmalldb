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


namespace Smalldb\StateMachine\Graph;


class GraphExportGrafovatko
{
	/**
	 * @var NestedGraph
	 */
	private $graph;

	/**
	 * @var callable function(Graph $graph, Graph $nestedGraph, array $exportedNestedGraph): array
	 */
	private $graphProcessor;

	/**
	 * @var callable function(Graph $graph, Node $node, array $exportedNode): array
	 */
	private $nodeAttrsProcessor;

	/**
	 * @var callable function(Graph $graph, Edge $edge, array $exportedEdge): array
	 */
	private $edgeAttrsProcessor;


	/**
	 * Global graph IDs prefix
	 *
	 * @var string
	 */
	private $prefix = '';


	/**
	 * GraphExportGrafovatko constructor.
	 *
	 * @param NestedGraph $graph
	 */
	public function __construct(NestedGraph $graph)
	{
		$this->graph = $graph;
		$this->graphProcessor = function(NestedGraph $nestedGraph, array $exportedGraph): array { return $exportedGraph; };
		$this->nodeAttrsProcessor = function(Node $node, array $exportedNode): array { return $exportedNode; };
		$this->edgeAttrsProcessor = function(Edge $edge, array $exportedEdge): array { return $exportedEdge; };
	}


	public function export(): array
	{
		return $this->exportGraph($this->graph);
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


	/**
	 * @param callable $graphProcessor  function(Graph $nestedGraph, array $exportedGraph): array
	 */
	public function setGraphProcessor(callable $graphProcessor): self
	{
		$this->graphProcessor = $graphProcessor;
		return $this;
	}


	/**
	 * Set a callable which will process each node.
	 *
	 * @param \Closure $nodeAttrsProcessor function(Node $node, array $exportedNode)
	 * @return GraphExportGrafovatko
	 */
	public function setNodeAttrsProcessor(callable $nodeAttrsProcessor): GraphExportGrafovatko
	{
		$this->nodeAttrsProcessor = $nodeAttrsProcessor;
		return $this;
	}


	/**
	 * Set a callable which will process each edge.
	 *
	 * @param \Closure $edgeAttrsProcessor function(Edge $edge, array $exportedEdge)
	 * @return GraphExportGrafovatko
	 */
	public function setEdgeAttrsProcessor(callable $edgeAttrsProcessor): GraphExportGrafovatko
	{
		$this->edgeAttrsProcessor = $edgeAttrsProcessor;
		return $this;
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
	private function exportGraph(NestedGraph $graph): array
	{
		$nodes = [];
		foreach ($graph->getNodes() as $node) {
			$nodeJson = [
				'id' => $this->prefix . $node->getId(),
				'graph' => $node->hasNestedGraph() ? $this->exportGraph($node->getNestedGraph()) : null,
				'attrs' => ($this->nodeAttrsProcessor)($node, $node->getAttributes()),
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
				'attrs' => ($this->edgeAttrsProcessor)($edge, $edge->getAttributes()),
			];
			if ($edgeJson !== null) {
				$edges[] = $edgeJson;
			}
		}

		return ($this->graphProcessor)($graph, [
			'layout' => 'dagre',
			'nodes' => $nodes,
			'edges' => $edges,
		]);
	}

}
