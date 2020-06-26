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

use Smalldb\StateMachine\AbstractMachine;
use Smalldb\Graph\Edge;
use Smalldb\Graph\Graph;
use Smalldb\Graph\GraphSearch;
use Smalldb\Graph\Node;


class GraphvizExport
{

	protected function renderBpmn($prefix, $fragment_file, $fragment, $errors)
	{
		/** @var Graph $graph */
		$graph = $fragment['graph'];

		// [visualization] Calculate distance of each node from nearest start
		// event to detect backward arrows and make diagrams look much better
		$distance = 0;
		GraphSearch::BFS($graph)
			->onNode(function(Node $cur_node) use (& $distance) {
				if (!isset($cur_node['_distance'])) {
					$cur_node['_distance'] = 0;
				}
				$distance = $cur_node['_distance'] + 1;
				return true;
			})
			->onEdge(function(Node $cur_node, Edge $edge, Node $next_node, bool $seen) use (& $distance) {
				if ($edge['type'] == 'messageFlow') {
					$nextNodeDistance = $next_node->getAttr('_distance', INF);
					if ($nextNodeDistance > $distance) {
						$next_node->setAttr('_distance', $distance);
					}
					return false;
				} else if ($edge['type'] == 'sequenceFlow' && !$seen) {
					$next_node->setAttr('_distance', $distance);
					return true;
				} else {
					return false;
				}
			})
			->start($graph->getNodesByAttr('type', 'startEvent'));

		$diagram = "\tsubgraph cluster_$prefix {\n\t\tlabel= \"BPMN:\\n".basename($fragment_file)."\"; color=\"#5373B4\";\n\n";

		// Draw arrows
		foreach ($graph->getAllEdges() as $id => $a) {
			$sourceNode = $a->getStart();
			$targetNode = $a->getEnd();
			$sourceId = $sourceNode->getId();
			$targetId = $targetNode->getId();
			$source = AbstractMachine::exportDotIdentifier($sourceId, $prefix);
			$target = AbstractMachine::exportDotIdentifier($targetId, $prefix);
			if (!$sourceId || !$targetId) {
				var_dump($id);
				var_dump($a);
			}
			$backwards = ($sourceNode['_distance'] >= $targetNode['_distance'])
				&& $targetNode['_distance'];
			$label = $a['name'];
			if ($a['_generated'] && $label != '') {
				$label = "($label)";
			}
			$tooltip = json_encode($a->getAttributes(), JSON_NUMERIC_CHECK|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

			$diagram .= "\t\t" . $source . ' -> ' . $target
				. " [id=\"" . addcslashes($prefix.$id, "\"") . "\""
				. ",tooltip=\"" . addcslashes($tooltip, "\"") . "\"";

			if ($backwards) {
				$diagram .= ',constraint=0';
			}
			if ($a['_transition']) {
				$color = '#2266cc';
			} else if ($a['_state']) {
				$color = '#66aa22';
			} else {
				$color = '#666666';
			}
			$alpha = ($a['_generated'] ? '66' : '');

			$diagram .= ",xlabel=<<font color=\"$color$alpha\"> ".nl2br(htmlspecialchars($label))." </font>>";
			switch ($a['type']) {
				case 'sequenceFlow':
					$w = $backwards ? 3 : 5;
					$diagram .= ',style="dotted"';
					if ($a['_dependency_only']) {
						$diagram .= ',style="invis"';
						$w = 0;
					} else {
						$diagram .= ',style="solid"';
					}
					break;
				case 'messageFlow':
					$diagram .= ',style="dashed",arrowhead=empty,arrowtail=odot';
					// Bug: Graphviz can't combine rang and clusters
					//$w = $backwards ? 0 : 1;	// Desired weights
					//$diagram .= ',constraint=0';	// Workaround
					$w = 0;				// Another workaround
					break;
				case 'association':
					$color = '#aaaaaa';
					$diagram .= ',style="dashed",arrowhead=none';
					$w = 1;
					break;
				case 'error':
					$color = '#ff0000';
					$diagram .= ',style="dashed",arrowhead=none';
					$w = 1;
					break;
				default:
					$color = '#ff0000';
					$w = 1;
					break;
			}

			$diagram .= ",color=\"$color$alpha\",fontcolor=\"#aaaaaa$alpha\"";
			$diagram .= ",weight=$w";
			$diagram .= "];\n";

			$nodes[$source] = $sourceId;
			$nodes[$target] = $targetId;
		}

		$diagram .= "\n";

		// Draw nodes
		foreach ($graph->getAllNodes() as $id => $n) {
			$graph_id = AbstractMachine::exportDotIdentifier($id, $prefix);

			// Render node
			$label = trim($n['name']);
			if ($n['_generated'] && $label != '') {
				$label = "($label)";
			}
			$alpha = ($n['_generated'] ? '66' : '');
			$tooltip = json_encode($n->getAttributes(), JSON_NUMERIC_CHECK|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
			$diagram .= "\t\t" . $graph_id
				. " [id=\"" . addcslashes($prefix.$id, "\"\n") . "\""
				. ",tooltip=\"".addcslashes($tooltip, "\"")."\"";

			switch ($n['type']) {
				case 'startEvent':
				case 'endEvent':
				case 'intermediateCatchEvent':
				case 'intermediateThrowEvent':
					if ($n['name'] != $id) {
						$diagram .= ",xlabel=<<font color=\"#aaaaaa$alpha\"> ".htmlspecialchars($label)." </font>>";
					}
					if (isset($n['features']['timerEventDefinition'])) {
						$diagram .= ",label=\"T\"";
					} else if (isset($n['features']['messageEventDefinition'])) {
						$diagram .= ",label=\"M\"";
					} else {
						$diagram .= ",label=\"\"";
					}
					break;
				case 'textAnnotation':
					$diagram .= ",shape=note,fillcolor=\"#ffffff$alpha\",fontcolor=\"#888888$alpha\""
						. ",label=\"".addcslashes(wordwrap($n['text'], 32), "\"\n")."\"";
					break;
				default:
					$diagram .= ",label=\"".addcslashes($label, "\"\n")."\"";
					break;
			}
			switch ($n['type']) {
				case 'task': $diagram .= ",shape=box,style=\"rounded,filled\",fillcolor=\"#eeeeee$alpha\""; break;
				case 'participant': $diagram .= ',shape=box,style=filled,fillcolor="#ffffff'.$alpha.'",penwidth=2'; break;
				case 'startEvent': $diagram .= ',shape=circle,width=0.4,height=0.4,root=1'; break;
				case 'intermediateCatchEvent': $diagram .= ',shape=doublecircle,width=0.35'; break;
				case 'intermediateThrowEvent': $diagram .= ',shape=doublecircle,width=0.35'; break;
				case 'endEvent': $diagram .= ',shape=circle,width=0.4,height=0.4,penwidth=3'; break;
				case 'exclusiveGateway': $diagram .= ',shape=diamond,style=filled,height=0.5,width=0.5,label="X"'; break;
				case 'parallelGateway': $diagram .= ',shape=diamond,style=filled,height=0.5,width=0.5,label="+"'; break;
				case 'inclusiveGateway': $diagram .= ',shape=diamond,style=filled,height=0.5,width=0.5,label="O"'; break;
				case 'complexGateway': $diagram .= ',shape=diamond,style=filled,height=0.5,width=0.5,label="*"'; break;
				case 'eventBasedGateway': $diagram .= ',shape=diamond,style=filled,height=0.5,width=0.5,label="E"'; break;
			}

			// Node color
			if ($n['_transition']) {
				$diagram .= ",color=\"#2266cc$alpha\"";
			} else if ($n['_state']) {
				$diagram .= ",color=\"#66aa22$alpha\"";
			} else if ($n['type'] == 'textAnnotation') {
				$diagram .= ",color=\"#aaaaaa$alpha\"";
			} else {
				$diagram .= ",color=\"#000000$alpha\"";
			}

			// Receiving/invoking background
			if ($n['_invoking'] && $n['_receiving']) {
				$diagram .= ",fillcolor=\"#ffff88$alpha;0.5:#aaddff$alpha\",gradientangle=270";
			} else if ($n['_invoking']) {
				$diagram .= ",fillcolor=\"#ffff88$alpha\"";
			} else if ($n['_receiving']) {
				$diagram .= ",fillcolor=\"#aaddff$alpha\"";
			} else if ($n['_potential_receiving']) {
				$diagram .= ",fillcolor=\"#eeeeee$alpha;0.5:#aaddff$alpha\",gradientangle=270";
			}

			// End of node.
			$diagram .= "];\n";
		}

		// Render errors
		foreach ($errors as $err) {
			$m = AbstractMachine::exportDotIdentifier('error_'.md5($err['text']), $prefix);
			$diagram .= "\t\t\"$m\" [color=\"#ff0000\",fillcolor=\"#ffeeee\",label=\"".addcslashes($err['text'], "\n\"")."\"];\n";
			foreach ($err['nodes'] as $n) {
				/** @var Node $n */
				$nn = AbstractMachine::exportDotIdentifier($n->getId(), $prefix);
				$diagram .= "\t\t$nn -> \"$m\":n [color=\"#ffaaaa\",style=dashed,arrowhead=none];\n";
			}
		}

		//echo "<pre>", $diagram, "</pre>";

		$diagram .= "\t}\n";

		return $diagram;
	}
}
