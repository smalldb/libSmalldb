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

namespace Smalldb\StateMachine\Definition\Renderer;

use Smalldb\StateMachine\Definition\StateMachineGraph\StateMachineEdge;
use Smalldb\StateMachine\Definition\StateMachineGraph\StateMachineNode;
use Smalldb\StateMachine\Graph\Edge;
use Smalldb\StateMachine\Graph\Grafovatko\ProcessorInterface;
use Smalldb\StateMachine\Graph\Graph;
use Smalldb\StateMachine\Graph\NestedGraph;
use Smalldb\StateMachine\Graph\Node;


class StateMachineProcessor implements ProcessorInterface
{

	/**
	 * Returns modified $exportedGraph which become the graph's attributes.
	 */
	public function processGraph(NestedGraph $graph, array $exportedGraph, string $prefix): array
	{
		return $exportedGraph;
	}


	/**
	 * Returns modified $exportedNode which become the node's attributes.
	 */
	public function processNodeAttrs(Node $node, array $exportedNode, string $prefix): array
	{
		if ($node instanceof StateMachineNode) {
			$state = $node->getState();
			$stateName = $state->getName();
			$exportedNode['label'] = $stateName;

			if ($stateName === '') {
				if ($node->isSourceNode()) {
					$exportedNode['shape'] = 'uml.initial_state';
				} else if (empty($node->getConnectedEdges())) {
					$exportedNode['shape'] = 'none';
				} else {
					$exportedNode['shape'] = 'uml.final_state';
				}
			} else {
				$exportedNode['shape'] = 'uml.state';
			}

			// TODO: Add color support
			$exportedNode['fill'] = "#eee";

			// TODO: Highlight unreachable and undefined states

		}
		return $exportedNode;
	}


	/**
	 * Returns modified $exportedEdge which become the edge's attributes.
	 */
	public function processEdgeAttrs(Edge $edge, array $exportedEdge, string $prefix): array
	{
		if ($edge instanceof StateMachineEdge) {
			$transition = $edge->getTransition();
			$exportedEdge['label'] = $transition->getName();
		}
		return $exportedEdge;
	}

	/**
	 * Returns Htag-style array of additional SVG elements which will be appended to the rendered SVG image.
	 */
	public function getExtraSvgElements(Graph $graph, $prefix): array
	{
		return [];
	}
}
