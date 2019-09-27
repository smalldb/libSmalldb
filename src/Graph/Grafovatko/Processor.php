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


/**
 * A dummy graph processor which implements no-op on everything.
 */
class Processor implements ProcessorInterface
{
	/** @var string */
	private $prefix;


	public function setPrefix(string $prefix): void
	{
		$this->prefix = $prefix;
	}


	/**
	 * Returns modified $exportedGraph which become the graph's attributes.
	 *
	 * @param NestedGraph $graph
	 * @param array $exportedGraph
	 * @return array
	 */
	public function processGraph(NestedGraph $graph, array $exportedGraph): array
	{
		return $exportedGraph;  // @codeCoverageIgnore
	}

	/**
	 * Returns modified $exportedNode which become the node's attributes.
	 */
	public function processNodeAttrs(Node $node, array $exportedNode): array
	{
		return $exportedNode;  // @codeCoverageIgnore
	}

	/**
	 * Returns modified $exportedEdge which become the edge's attributes.
	 */
	public function processEdgeAttrs(Edge $edge, array $exportedEdge): array
	{
		return $exportedEdge;  // @codeCoverageIgnore
	}

	/**
	 * Returns Htag-style array of additional SVG elements which will be appended to the rendered SVG image.
	 */
	public function getExtraSvgElements(Graph $graph): array
	{
		return [];  // @codeCoverageIgnore
	}
}
