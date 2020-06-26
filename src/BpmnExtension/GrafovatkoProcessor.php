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

namespace Smalldb\StateMachine\BpmnExtension;

use Smalldb\Graph\Edge;
use Smalldb\Graph\Grafovatko\ProcessorInterface;
use Smalldb\Graph\Graph;
use Smalldb\Graph\NestedGraph;
use Smalldb\Graph\Node;


class GrafovatkoProcessor implements ProcessorInterface
{
	/** @var string */
	private $prefix = '';

	/** @var string|null */
	private $targetParticipant = null;


	public function __construct(?string $targetParticipant = null)
	{
		$this->targetParticipant = $targetParticipant;
	}


	public function setPrefix(string $prefix): void
	{
		$this->prefix = $prefix;
	}


	public function setTargetParticipant(?string $targetParticipant): void
	{
		$this->targetParticipant = $targetParticipant;
	}


	/**
	 * Returns modified $exportedGraph which become the graph's attributes.
	 */
	public function processGraph(NestedGraph $graph, array $exportedGraph): array
	{
		$parentNode = $graph->getParentNode();
		$parentNodeType = $parentNode ? $parentNode->getAttr('type') : null;

		if (!$parentNode || $parentNodeType === 'bpmnDiagram') {
			$exportedGraph['layout'] = 'column';
			$exportedGraph['layoutOptions'] = [
				'sortNodes' => true,
			];
		} else if ($parentNodeType === 'participant') {
			if ($parentNode->getAttr('_is_state_machine', false)) {
				$exportedGraph['layout'] = 'row';
				$exportedGraph['layoutOptions'] = [
					'sortNodes' => 'attr',
					'sortAttr' => '_distance',
					'arcEdges' => false,
				];
			} else {
				$exportedGraph['layout'] = 'dagre';
				$exportedGraph['layoutOptions'] = [
					'rankdir' => 'LR',
				];
			}
		}
		return $exportedGraph;
	}

	/**
	 * Returns modified $exportedNode which become the node's attributes.
	 */
	public function processNodeAttrs(Node $node, array $exportedNode): array
	{
		$exportedNode['fill'] = "#fff";

		// Node label
		if (isset($node['name'])) {
			$label = trim($node['name']);
			if ($node['_generated'] && $label != '') {
				$label = "($label)";
			}
			$exportedNode['label'] = $label;
		}

		if ($node['type'] != 'participant') {
			$exportedNode['tooltip'] = json_encode($node->getAttributes(), JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		}

		// Node type (symbol)
		switch ($node['type']) {
			case 'bpmnDiagram':
				$exportedNode['color'] = "#5373B4";
				break;

			case 'task':
			case 'sendTask':
			case 'receiveTask':
			case 'userTask':
			case 'serviceTask':
				$exportedNode['shape'] = 'bpmn.task';
				break;

			case 'participant':
				if ($this->targetParticipant && $node->getId() === $this->targetParticipant) {
					$exportedNode['fill'] = "#ddffbb";
				}
				break;

			case 'startEvent':
			case 'intermediateCatchEvent':
			case 'intermediateThrowEvent':
			case 'endEvent':
				$exportedNode['shape'] = 'bpmn.event';
				$exportedNode['event_type'] = ['startEvent' => 'start', 'endEvent' => 'end'][$node['type']] ?? 'intermediate';
				$exportedNode['event_is_throwing'] = ($node['type'] == 'intermediateThrowEvent');
				if (isset($node['features']['timerEventDefinition'])) {
					$exportedNode['event_symbol'] = 'timer';
				} else {
					if (isset($node['features']['messageEventDefinition'])) {
						$exportedNode['event_symbol'] = 'message';
					}
				}
				break;

			case 'exclusiveGateway':
				$exportedNode['shape'] = 'bpmn.gateway';
				$exportedNode['gateway_type'] = 'exclusive';
				break;

			case 'eventBasedGateway':
				$exportedNode['shape'] = 'bpmn.gateway';
				$exportedNode['gateway_type'] = 'event';
				break;

			case 'parallelEventBasedGateway':
				$exportedNode['shape'] = 'bpmn.gateway';
				$exportedNode['gateway_type'] = 'parallel_event';
				break;

			case 'inclusiveGateway':
				$exportedNode['shape'] = 'bpmn.gateway';
				$exportedNode['gateway_type'] = 'inclusive';
				break;

			case 'complexGateway':
				$exportedNode['shape'] = 'bpmn.gateway';
				$exportedNode['gateway_type'] = 'complex';
				break;

			case 'parallelGateway':
				$exportedNode['shape'] = 'bpmn.gateway';
				$exportedNode['gateway_type'] = 'parallel';
				break;

			case 'textAnnotation':
				$exportedNode['shape'] = 'note';
				$exportedNode['label'] = $node['text'];
				$exportedNode['color'] = '#aaaaaa';
				break;

			case 'error':
				$exportedNode['label'] = $node['label'];
				$exportedNode['shape'] = 'rect';
				$exportedNode['color'] = '#ff0000';
				$exportedNode['fill'] = '#ffeeee';
				break;
		}

		// Low opacity for generated and removed nodes
		if ($node['_generated'] || !empty($node['_unused'])) {
			$exportedNode['opacity'] = '0.4';
		}

		// Node color
		if ($node['_transition']) {
			$exportedNode['color'] = '#2266cc';
		} else if ($node['_state']) {
			$exportedNode['color'] = '#66aa22';
		}

		// Receiving/invoking background
		if ($node['_invoking'] && $node['_receiving']) {
			$exportedNode['fill'] = 'url(#' . $this->prefix . '_gradient_rcv_inv)';
		} else if ($node['_invoking']) {
			$exportedNode['fill'] = '#ffff88';
		} else if ($node['_receiving']) {
			$exportedNode['fill'] = '#aaddff';
		} else if ($node['_potential_receiving']) {
			$exportedNode['fill'] = '#eeeeff';
			$exportedNode['fill'] = 'url(#' . $this->prefix . '_gradient_pos_rcv)';
		}

		return $exportedNode;
	}

	/**
	 * Returns modified $exportedEdge which become the edge's attributes.
	 */
	public function processEdgeAttrs(Edge $edge, array $exportedEdge): array
	{
		if (isset($edge['name'])) {
			$label = trim($edge['name']);
			if ($edge['_generated'] && $label != '') {
				$label = "($label)";
			}
			$exportedEdge['label'] = $label;
		}

		$exportedEdge['tooltip'] = json_encode($edge->getAttributes(),
			JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		// Low opacity for generated or removed edges
		if ($edge['_generated'] || !empty($edge['_unused'])) {
			$exportedEdge['opacity'] = '0.4';
		}

		// Edge color
		if ($edge['_transition']) {
			$exportedEdge['color'] = "#2266cc";
		} else {
			if ($edge['_state']) {
				$exportedEdge['color'] = "#66aa22";
			} else {
				$exportedEdge['color'] = "#666666";
			}
		}

		// Edge style
		switch ($edge['type']) {
			case 'sequenceFlow':
				if ($edge['_dependency_only']) {
					$exportedEdge['hidden'] = true;
				}
				break;
			case 'messageFlow':
				$exportedEdge['stroke_dasharray'] = '5,4';
				$exportedEdge['arrowHead'] = 'empty';
				$exportedEdge['arrowTail'] = 'odot';
				break;
			case 'association':
				$exportedEdge['color'] = '#aaaaaa';
				$exportedEdge['stroke_dasharray'] = '5,4';
				$exportedEdge['arrowHead'] = 'none';
				break;
			case 'error':
				$exportedEdge['color'] = '#ff0000';
				$exportedEdge['stroke_dasharray'] = '5,4';
				$exportedEdge['arrowHead'] = 'none';
				break;
			default:
				$exportedEdge['color'] = '#ff0000';
				break;
		}

		return $exportedEdge;
	}


	public function getExtraSvgElements(Graph $graph): array
	{
		return [
			['defs', [], [
				['linearGradient', ['id' => $this->prefix . '_gradient_rcv_inv'], [
					['stop', ['offset' => '50%', 'stop-color' => '#ff8']],
					['stop', ['offset' => '50%', 'stop-color' => '#adf']],
				]],
				['linearGradient', ['id' => $this->prefix . '_gradient_pos_rcv'], [
					['stop', ['offset' => '50%', 'stop-color' => '#fff']],
					['stop', ['offset' => '50%', 'stop-color' => '#adf']],
				]],
			]],
		];
	}

}
