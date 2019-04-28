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

namespace Smalldb\StateMachine\Graph\Grafovatko;

use Smalldb\StateMachine\Graph\Edge;
use Smalldb\StateMachine\Graph\Graph;
use Smalldb\StateMachine\Graph\NestedGraph;
use Smalldb\StateMachine\Graph\Node;


class ClosureProcessor implements ProcessorInterface
{
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
	 * @var callable function(Graph $graph, string $prefix): array
	 */
	private $extraSvgElementsProcessor;


	public function __construct()
	{
		$this->graphProcessor = function(NestedGraph $nestedGraph, array $exportedGraph, string $prefix): array { return $exportedGraph; };
		$this->nodeAttrsProcessor = function(Node $node, array $exportedNode, string $prefix): array { return $exportedNode; };
		$this->edgeAttrsProcessor = function(Edge $edge, array $exportedEdge, string $prefix): array { return $exportedEdge; };
		$this->extraSvgElementsProcessor = function(Graph $graph, string $prefix): array { return []; };
	}


	/**
	 * @param \Closure $graphProcessor  function(Graph $nestedGraph, array $exportedGraph): array
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
	 */
	public function setNodeAttrsProcessor(callable $nodeAttrsProcessor): self
	{
		$this->nodeAttrsProcessor = $nodeAttrsProcessor;
		return $this;
	}


	/**
	 * Set a callable which will process each edge.
	 *
	 * @param \Closure $edgeAttrsProcessor function(Edge $edge, array $exportedEdge)
	 */
	public function setEdgeAttrsProcessor(callable $edgeAttrsProcessor): self
	{
		$this->edgeAttrsProcessor = $edgeAttrsProcessor;
		return $this;
	}


	/**
	 * Set a callable which will process each edge.
	 *
	 * @param \Closure $edgeAttrsProcessor function(Edge $edge, array $exportedEdge)
	 */
	public function setExtraSvgElementsProcessor(callable $extraSvgElementsProcessor): self
	{
		$this->extraSvgElementsProcessor = $extraSvgElementsProcessor;
		return $this;
	}


	/**
	 * Returns modified $exportedGraph which become the graph's attributes.
	 */
	public function processGraph(NestedGraph $graph, array $exportedGraph, string $prefix): array
	{
		return ($this->graphProcessor)($graph, $exportedGraph, $prefix);
	}


	/**
	 * Returns modified $exportedNode which become the node's attributes.
	 */
	public function processNodeAttrs(Node $node, array $exportedNode, string $prefix): array
	{
		return ($this->nodeAttrsProcessor)($node, $exportedNode, $prefix);
	}


	/**
	 * Returns modified $exportedEdge which become the edge's attributes.
	 */
	public function processEdgeAttrs(Edge $edge, array $exportedEdge, string $prefix): array
	{
		return ($this->edgeAttrsProcessor)($edge, $exportedEdge, $prefix);
	}

	/**
	 * Returns Htag-style array of additional SVG elements which will be appended to the rendered SVG image.
	 */
	public function getExtraSvgElements(Graph $graph, $prefix): array
	{
		return ($this->extraSvgElementsProcessor)($graph, $prefix);
	}
}
