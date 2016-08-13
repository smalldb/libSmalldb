<?php
/*
 * Copyright (c) 2016, Josef Kufner  <josef@kufner.cz>
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

namespace Smalldb\StateMachine;

/**
 * BPMN reader
 *
 * Read BPMN diagram and create state machine which implements given business
 * proces. When multiple BPMN loaders used, the final state machine will
 * implement all of the business processes.
 *
 * The first step is to load all BPMN diagrams, but not to update state machine
 * definition. The second step is performed during machine definition
 * postprocessing, when all BPMN diagrams are combined together and state
 * machine definition is generated.
 *
 * Options:
 * 
 *   - `process_id`: ID of BPMN process to use from the file (string). If
 *     null, the first process is used.
 *
 * @see https://camunda.org/bpmn/tool/
 */
class BpmnReader implements IMachineDefinitionReader
{

	/// @copydoc IMachineDefinitionReader::loadString
	public static function loadString($data_string, $options = [], $filename = null)
	{
		// Options
		$bpmn_process_id = isset($options['process_id']) ? $options['process_id'] : null;

		// Load GraphML into DOM
		$dom = new \DOMDocument;
		$dom->loadXml($data_string);

		// Prepare XPath query engine
		$xpath = new \DOMXpath($dom);
		$xpath->registerNameSpace('bpmn', 'http://www.omg.org/spec/BPMN/20100524/MODEL');

		if ($bpmn_process_id) {
			if (!preg_match('/^[a-zA-Z0-9_.-]*$/', $bpmn_process_id)) {
				throw new BpmnException('Invalid process ID (only alphanumeric characters, underscore, dot and dash are allowed): '.var_export($bpmn_process_id, true));
			}
			$machine_process_element = $xpath->query('/bpmn:definitions/bpmn:process[@id=\''.$bpmn_process_id.'\']')->item(0);
		} else {
			$machine_process_element = $xpath->query('/bpmn:definitions/bpmn:process')->item(0);
		}

		// Process element is mandatory
		if (!$machine_process_element) {
			throw new BpmnException('Process element not found: '.var_export($bpmn_process_id, true));
		}

		// Lets collect arrows, events and tasks (still in BPMN semantics)
		$arrows = [];
		$nodes = [];
		$groups = [];

		// Get processes (groups)
		foreach($xpath->query('//bpmn:process[@id]') as $el) {
			$id = $el->getAttribute('id');
			$name = $el->getAttribute('name');

			$groups[$id] = [
				'id' => $id,
				'name' => $name == '' ? $id : $name,
				'nodes' => [],
			];
		}

		// Get arrows
		foreach (['sequenceFlow', 'messageFlow'] as $type) {
			foreach($xpath->query('//bpmn:'.$type.'[@id][@sourceRef][@targetRef]') as $el) {
				// Arrow properties
				$id = $el->getAttribute('id');
				$name = $el->getAttribute('name');
				$sourceRef = $el->getAttribute('sourceRef');
				$targetRef = $el->getAttribute('targetRef');

				// Get process where the arrow belongs
				$process_element = $el->parentNode;
				if ($process_element && $process_element->tagName == 'bpmn:process') {
					$process_id = $process_element->getAttribute('id');
				} else {
					$process_id = null;
				}

				// Store arrow
				$arrows[$id] = [
					'id' => $id,
					'type' => $type,
					'source' => $sourceRef,
					'target' => $targetRef,
					'name' => $name,
					'process' => $process_id,
				];
			}
		}

		// Get event nodes
		foreach (['startEvent', 'task', 'intermediateThrowEvent', 'endEvent',
			'exclusiveGateway', 'parallelGateway', 'inclusiveGateway', 'complexGateway', 'eventBasedGateway'] as $type)
		{
			foreach($xpath->query('//bpmn:'.$type.'[@id]') as $el) {
				$id = $el->getAttribute('id');
				$name = $el->getAttribute('name');
				if ($name == '') {
					$name = $id;
				}

				// Get process where the arrow belongs
				$process_element = $el->parentNode;
				if ($process_element && $process_element->tagName == 'bpmn:process') {
					$process_id = $process_element->getAttribute('id');
					$groups[$process_id]['nodes'][] = $id;
				} else {
					$process_id = null;
				}

				$incoming = [];
				foreach($xpath->query('./bpmn:incoming/text()[1]', $el) as $in) {
					$incoming[] = $in->wholeText;
				}

				$outgoing = [];
				foreach($xpath->query('./bpmn:outgoing/text()[1]', $el) as $out) {
					$outgoing[] = $out->wholeText;
				}

				$nodes[$id] = [
					'id' => $id,
					'name' => $name,
					'type' => $type,
					'process' => $process_id,
					'incoming' => $incoming,
					'outgoing' => $outgoing,
				];
			}
		}

		// Store fragment in state machine definition
		return [
			'bpmn_fragments' => [
				$filename.'#'.$bpmn_process_id => [
					'file' => $filename,
					'process_id' => $bpmn_process_id,
					'arrows' => $arrows,
					'nodes' => $nodes,
					'groups' => $groups,
				],
			],
		];

		///---------------

		// Dump BPMN fragment
		/*
		printf("\n<pre>BPMN diagram: %s, %s\n", basename($filename), $bpmn_process_id);
		printf("  Nodes:\n");
		foreach ($nodes as $id => $n) {
			printf("    %s (%s)\n", var_export($n['name'], true), var_export($id, true));
			foreach ($n['incoming'] as $in) {
				printf("        In:  %s\n", $in);
			}
			foreach ($n['outgoing'] as $out) {
				printf("        Out: %s\n", $out);
			}
		}
		printf("  Arrows:\n");
		foreach ($arrows as $id => $a) {
			printf("    %25s --> %-25s (%s, %s)\n", $a['source'], $a['target'], var_export($id, true), var_export($a['name'], true));
		}
		printf("</pre>\n");
		// */

	}


	/// @copydoc IMachineDefinitionReader::postprocessDefinition
	public static function postprocessDefinition(& $machine_def)
	{
		if (!isset($machine_def['bpmn_fragments'])) {
			return;
		}
		$bpmn_fragments = $machine_def['bpmn_fragments'];
		unset($machine_def['bpmn_fragments']);

		$states = [];
		$actions = [];

		// Each included BPMN file provided one fragment
		foreach ($bpmn_fragments as $fragment_file => $fragment) {
			$primary_process_id = $fragment['process_id'];
			$prefix = "bpmn_".(0xffff & crc32($fragment_file)).'_';
			$diagram = "\tsubgraph cluster_$prefix {\n\t\tlabel= \"BPMN:\\n".basename($fragment_file)."\"; color=\"#5373B4\";\n\n";

			// Calculate neighbour nodes
			$next_node = [];
			foreach ($fragment['arrows'] as $id => $a) {
				if ($a['type'] == 'sequenceFlow') {
					$next_node[$a['source']][] = $a['target'];
				}
			}

			// Collect start nodes
			$start_nodes = [];
			foreach ($fragment['nodes'] as $id => $n) {
				if ($n['type'] == 'startEvent') {
					$start_nodes[] = $id;
					$fragment['nodes'][$id]['_distance'] = 0;
				}
			}

			// Calculate distance of each node from nearest start event to detect backward arrows (DFS)
			$queue = $start_nodes;
			while (!empty($queue)) {
				$id = array_pop($queue);
				$distance = $fragment['nodes'][$id]['_distance'] + 1;
				if (isset($next_node[$id])) {
					foreach ($next_node[$id] as $next_id) {
						$n = $fragment['nodes'][$next_id];
						if (!isset($n['_distance'])) {
							$fragment['nodes'][$next_id]['_distance'] = $distance;
							$queue[] = $next_id;
						}
					}
				}
			}

			// Draw arrows
			foreach ($fragment['arrows'] as $id => $a) {
				$source = AbstractMachine::exportDotIdentifier($a['source'], $prefix);
				$target = AbstractMachine::exportDotIdentifier($a['target'], $prefix); 
				$backwards = ($fragment['nodes'][$a['source']]['_distance'] >= $fragment['nodes'][$a['target']]['_distance']);
				$diagram .= "\t\t" . $source . ' -> ' . $target. ' [tooltip="'.addcslashes($a['id'], '"').'"';
				if ($backwards) {
					$diagram .= ',constraint=0';
				}
				switch ($a['type']) {
					case 'sequenceFlow':
						$diagram .= 'style=solid,color="#666666"';
						$w = $backwards ? 3 : 5;
						break;
					case 'messageFlow':
						$diagram .= 'style=dashed,color="#666666",arrowhead=empty,arrowtail=odot';
						$w = 0;
						break;
					default: $diagram .= 'color=red'; break;
				}
				if ($a['process'] == $primary_process_id) {
					$diagram .= ',color="#44aa44",penwidth=1.5';
				}
				$diagram .= ",weight=$w];\n";

				$nodes[$source] = $a['source'];
				$nodes[$target] = $a['target'];
			}

			$diagram .= "\n";

			// Draw nodes
			foreach ($fragment['nodes'] as $id => $n) {
				$graph_id = AbstractMachine::exportDotIdentifier($id, $prefix);

				$diagram .= "\t\t" . $graph_id . " [label=\"".addcslashes($n['name'], '"')."\",tooltip=\"".addcslashes($n['id'], '"')."\"";
				switch ($n['type']) {
					case 'startEvent': $diagram .= ',shape=circle,width=0.4,height=0.4,label="",root=1'; break;
					case 'intermediateThrowEvent': $diagram .= ',shape=doublecircle,width=0.35,label=""'; break;
					case 'endEvent': $diagram .= ',shape=circle,width=0.4,height=0.4,penwidth=3,label=""'; break;
					case 'exclusiveGateway': $diagram .= ',shape=diamond,style=filled,height=0.5,width=0.5,label="X"'; break;
					case 'parallelGateway': $diagram .= ',shape=diamond,style=filled,height=0.5,width=0.5,label="+"'; break;
					case 'inclusiveGateway': $diagram .= ',shape=diamond,style=filled,height=0.5,width=0.5,label="O"'; break;
					case 'complexGateway': $diagram .= ',shape=diamond,style=filled,height=0.5,width=0.5,label="*"'; break;
					case 'eventBasedGateway': $diagram .= ',shape=diamond,style=filled,height=0.5,width=0.5,label="E"'; break;
				}
				if ($n['process'] == $primary_process_id) {
					$diagram .= ',color="#44aa44"';
				}
				$diagram .= "];\n";
			}

			foreach ($fragment['groups'] as $id => $g) {
				$graph_id = AbstractMachine::exportDotIdentifier($id, $prefix);

				$diagram .= "\n\t\tsubgraph cluster_$graph_id {\n\t\t\tlabel= \"".basename($g['name'])."\"; color=\"#aaaaaa\";\n\n";
				foreach ($g['nodes'] as $n_id) {
					$graph_n_id = AbstractMachine::exportDotIdentifier($n_id, $prefix);
					$diagram .= "\t\t\t".$graph_n_id.";\n";
				}
				$diagram .= "\t\t}\n";
			}


			// Cluster with fragment of the final state machine
			$diagram .= "\tsubgraph cluster_".$prefix."_sm {\n\t\tlabel= \"State machine\"; color=\"#B47353\"; fillcolor=\"#ffffee\"; style=filled;\n\n";

			// Walk from each task to next tasks, collecting state machine actions
			$paths = [];
			foreach ($fragment['nodes'] as $n_id => $n) {
				// If node is task and it is from the primary process
				if (($n['type'] == 'task' || $n['type'] == 'startEvent' || $n['type'] == 'endEvent') && $n['process'] == $primary_process_id) {
					// Add task to paths, so we know about all tasks
					if (!isset($paths[$n_id])) {
						$paths[$n_id] = [];
					}

					// Find all next tasks (DFS limited to non-task nodes)
					$queue = [$n_id];
					$visited = [$n_id => true];
					while (!empty($queue)) {
						$id = array_pop($queue);
						if (isset($next_node[$id])) {
							foreach ($next_node[$id] as $next_id) {
								$next_n = $fragment['nodes'][$next_id];
								if ($next_n['process'] == $primary_process_id) {
									if ($next_n['type'] == 'task' || $next_n['type'] == 'endEvent') {
										$paths[$n_id][] = $next_id;
									} else if (empty($visited[$next_id])) {
										$visited[$next_id] = true;
										$queue[] = $next_id;
									}
								}
							}
						}
					}
				}
			}

			// To invoke an action, we have to be in a state, so lets add a state in front of each action
			foreach ($paths as $src => $dst_list) {
				$n = $fragment['nodes'][$src];
				if ($n['type'] == 'task') {
					$states['before_'.$src] = [
					];
				}
			}

			// So the actions start in the newly assigned states...
			foreach ($paths as $src => $dst_list) {
				$src_n = $fragment['nodes'][$src];
				if ($src_n['type'] == 'startEvent') {
					$src_state = '';
				} else {
					$src_state = 'before_'.$src;
				}
				foreach ($dst_list as $dst) {
					$dst_n = $fragment['nodes'][$dst];
					if ($dst_n['type'] == 'endEvent') {
						$dst_state = '';
					} else {
						$dst_state = 'before_'.$dst;
					}
					$actions[$src_n['name']]['transitions'][$src_state]['targets'][] = $dst_state;
				}
			}
			var_dump($actions);

			// Draw found paths
			foreach ($paths as $src => $dst_list) {
				// Draw the action
				$n = $fragment['nodes'][$src];
				$graph_src = AbstractMachine::exportDotIdentifier($src, $prefix.'_action');
				$diagram .= $graph_src."[label=\"".addcslashes($n['name'], '"')."\"];\n";

				// Draw all follow-up actions
				foreach ($dst_list as $dst) {
					$source = AbstractMachine::exportDotIdentifier($src, $prefix.'_action');
					$target = AbstractMachine::exportDotIdentifier($dst, $prefix.'_action'); 
					$diagram .= "\t\t" . $source . ' -> ' . $target. ";\n";
				}
			}

			$diagram .= "\t}\n";

			//echo "<pre>", $diagram, "</pre>";




			$diagram .= "\t}\n";

			// Add BPMN diagram to state diagram
			$machine_def['state_diagram_extras'][] = $diagram;
		}

		// Update the definition
		$machine_def = array_replace_recursive([
				'states' => $states,
				'actions' => $actions,
			], $machine_def);
	}

}

