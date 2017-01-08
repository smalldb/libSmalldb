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
 *   - `state_machine_participant`: ID of BPMN participant to implement by
 *     Smalldb state machine.
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
		$state_machine_participant_id = isset($options['state_machine_participant_id']) ? $options['state_machine_participant_id'] : null;

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

		// Get nodes
		foreach (['participant', 'startEvent', 'task', 'intermediateThrowEvent', 'intermediateCatchEvent', 'endEvent',
			'exclusiveGateway', 'parallelGateway', 'inclusiveGateway', 'complexGateway', 'eventBasedGateway'] as $type)
		{
			foreach($xpath->query('//bpmn:'.$type.'[@id]') as $el) {
				$id = $el->getAttribute('id');
				$name = $el->getAttribute('name');
				if ($name == '') {
					$name = $id;
				}

				// Get process where the node belongs
				if (($processRef = $el->getAttribute('processRef'))) {
					$process_id = $processRef;
				} else if (($process_element = $el->parentNode) && $process_element->tagName == 'bpmn:process') {
					$process_id = $process_element->getAttribute('id');
				} else {
					$process_id = null;
				}
				if ($process_id) {
					$groups[$process_id]['nodes'][] = $id;
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
					'state_machine_participant_id' => $state_machine_participant_id,
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
		// Qb_* is Begin of a blue arrow
		// Qe_* is End of a blue arrow
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
			$state_machine_participant_id = $fragment['state_machine_participant_id'];
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
			$end_nodes = [];
			foreach ($fragment['nodes'] as $id => $n) {
				if ($n['type'] == 'startEvent') {
					$start_nodes[] = $id;
					$fragment['nodes'][$id]['_distance'] = 0;
				} else {
					// Mark all other nodes as unreachable (but define the distance)
					$fragment['nodes'][$id]['_distance'] = null;
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

			/*
			 * State machine synthesis
			 */

			// Find nodes connected to state machine participant
			$state_machine_participant_id = $fragment['state_machine_participant_id'];
			$invoking_actions = [];
			$receiving_nodes = [];
			$starting_nodes = [];
			$ending_nodes = [];
			foreach ($fragment['arrows'] as $a_id => $a) {
				if ($a['target'] == $state_machine_participant_id) {
					$invoking_actions[$a['source']] = $a;//$fragment['nodes'][$a['source']];
				}
				if ($a['source'] == $state_machine_participant_id) {
					$receiving_nodes[$a['target']] = $fragment['nodes'][$a['target']];
				}
			}

			// Find start & end nodes
			foreach ($fragment['nodes'] as $n_id => $n) {
				if ($n['type'] == 'startEvent') {
					$starting_nodes[$n_id] = $n;
				} else if ($n['type'] == 'endEvent') {
					$ending_nodes[$n_id] = $n;
				}
			}

			// Find receiving nodes for each invoking node
			// (DFS to next task or waiting, the receiver cannot be further than that)
			$inv_rc_nodes = [];
			foreach ($invoking_actions as $in_id => $invoking_action) {
				$queue = [ $in_id ];
				$inv_rc_nodes[$in_id] = [];
				$in = $fragment['nodes'][$in_id];
				$cur_process = $in['process'];
				while (!empty($queue)) {
					$id = array_pop($queue);
					if (isset($next_node[$id])) {
						foreach ($next_node[$id] as $next_id) {
							$n = $fragment['nodes'][$next_id];
							if ($n['process'] != $cur_process || isset($invoking_actions[$next_id])) {
								// The receiving node must be within the same process and it must not be invoking node.
								continue;
							}
							if (isset($receiving_nodes[$next_id])) {
								$inv_rc_nodes[$in_id][$next_id] = $n;
							} else if (!isset($seen[$next_id]) && $fragment['nodes'][$next_id]['type'] != 'intermediateCatchEvent') {
								// Continue search if node is not visited nor is a catch event.
								$queue[] = $next_id;
								$seen[$next_id] = true;
							}
						}
					}
				}
				if (empty($inv_rc_nodes[$in_id])) {
					$inv_rc_nodes[$in_id][$in_id] = $in;	// Add implicit receiving arrow ...
					$receiving_nodes[$in_id] = $in;		// ... and make node receiving.
				}
			}

			// Find connections to next transition invocations
			$sm_next_node = [];
			foreach (array_merge($starting_nodes, $receiving_nodes) as $in_id => $in) {
				$seen = [ $in_id => true ];
				$queue = [ $in_id ];
				while (!empty($queue)) {
					$id = array_pop($queue);
					if (isset($next_node[$id])) {
						foreach ($next_node[$id] as $next_id) {
							if (isset($invoking_actions[$next_id]) || isset($receiving_nodes[$next_id]) || isset($ending_nodes[$next_id])) {
								if ($next_id != $in_id) {
									$sm_next_node[$in_id][] = $next_id;
								}
							} else if (!isset($seen[$next_id])) {
								$queue[] = $next_id;
								$seen[$next_id] = true;
							}
						}
					}
				}
			}

			$action_no = 1;
			$eq_states = [];

			// Add initial states
			foreach ($starting_nodes as $s_id => $s_n) {
				$q = 'Qe_'.$s_id;
				$states[$q] = [
					//'color' => '#ffccaa',
				];
			}

			// Build isolated fragments of the state machine (blue arrows; invoking--receiving groups)
			// Part 1/2: States
			$used_receiving_nodes = [];
			foreach ($invoking_actions as $in_id => $in_a) {
				$qi = 'Qb_'.$in_id;
				$states[$qi] = [
					//'color' => '#ffff88',
				];

				// Create used receiving nodes
				foreach ($inv_rc_nodes[$in_id] as $rcv_id => $rcv_n) {
					$qr = 'Qe_'.$rcv_id;
					$used_receiving_nodes[$rcv_id] = true;
					$states[$qr] = [
						//'color' => '#aaddff',
					];
				}
			}

			// Add final states
			foreach ($ending_nodes as $e_id => $e_n) {
				$q = 'Qb_'.$e_id;
				$states[$q] = [
					//'color' => '#ffccaa',
				];
			}

			// Connect fragments of the state machine (green arrows)
			foreach ($sm_next_node as $src => $dst_list) {
				foreach ($dst_list as $dst) {
					$eq_states['Qe_'.$src][] = 'Qb_'.$dst;
				}
			}

			// Create unused receiving nodes and add green arrows on them (as they are pass-through)
			foreach ($receiving_nodes as $rcv_id => $rcv) {
				if (empty($used_receiving_nodes[$rcv_id])) {
					$qb = 'Qb_'.$rcv_id;
					$qe = 'Qe_'.$rcv_id;
					if (!isset($states[$qb])) {
						$states[$qb] = [];
					}
					if (!isset($states[$qe])) {
						$states[$qe] = [];
					}
					$eq_states[$qb][] = $qe;
				}
			}

			// Build replacement table
			$uf = new UnionFind();
			$uf->add('');
			foreach ($states as $s_id => $s) {
				$uf->add($s_id);
			}
			foreach ($starting_nodes as $s_id => $s_n) {
				$uf->union('', 'Qe_'.$s_id);
			}
			foreach ($eq_states as $src => $dst_list) {
				foreach ($dst_list as $dst) {
					if (!isset($states[$src]) || !isset($states[$dst])) {
						//throw new \RuntimeException("Source state \"$src\" is not defined, something is wrong - can't merge \"$src\" with \"$dst\".");
						$s = AbstractMachine::exportDotIdentifier(preg_replace('/^Q[eb]_/', '', $src), $prefix);
						$d = AbstractMachine::exportDotIdentifier(preg_replace('/^Q[eb]_/', '', $dst), $prefix);
						$m = addcslashes("Can't merge states:\n"
							."\"$src\"".(isset($states[$src]) ? "" : " - not defined")."\n"
							."\"$dst\"".(isset($states[$dst]) ? "" : " - not defined"), "\n\"");
						$diagram .= "\t\t\"$m\" [color=\"#ff0000\",fillcolor=\"#ffeeee\"];\n";
						$diagram .= "\t\t$s -> \"$m\":n [color=\"#ffaaaa\",style=dashed,arrowhead=none];\n";
						$diagram .= "\t\t$d -> \"$m\":n [color=\"#ffaaaa\",style=dashed,arrowhead=none];\n";
					} else {
						$uf->union($src, $dst);
					}
				}
			}
			foreach ($ending_nodes as $e_id => $e_n) {
				$uf->union('', 'Qb_'.$e_id);
			}

			$state_replace = $uf->findAll();
			/*
			echo "<pre>";
			foreach ($state_replace as $src => $dst) {
				if ($src == $dst) {
					echo "<span style=\"color:grey\">$src == $dst</span>\n";
				} else {
					echo "$src == $dst\n";
				}
			}
			echo "</pre>";
			// */

			// Build isolated fragments of the state machine (blue arrows; invoking--receiving groups)
			// Part 2/2: Actions
			foreach ($invoking_actions as $in_id => $in_a) {
				$qi = 'Qb_'.$in_id;
				foreach ($inv_rc_nodes[$in_id] as $rcv_id => $rcv_n) {
					$qr = 'Qe_'.$rcv_id;
					$inv_a = $invoking_actions[$in_id];
					$a = $inv_a['name'] ?: 'A'.($action_no++);

					$qir = $state_replace[$qi];
					$qrr = $state_replace[$qr];
					$actions[$a]['transitions'][$qir]['targets'][] = $qrr;
				}
			}

			// Remove merged states
			foreach ($state_replace as $src => $dst) {
				if ($src !== $dst) {
					unset($states[$src]);
				}
			}


			/*
			 * Render
			 */

			// Draw arrows
			foreach ($fragment['arrows'] as $id => $a) {
				$source = AbstractMachine::exportDotIdentifier($a['source'], $prefix);
				$target = AbstractMachine::exportDotIdentifier($a['target'], $prefix); 
				$backwards = ($fragment['nodes'][$a['source']]['_distance'] >= $fragment['nodes'][$a['target']]['_distance'])
						&& $fragment['nodes'][$a['target']]['_distance'];
				$diagram .= "\t\t" . $source . ' -> ' . $target. ' [tooltip="'.addcslashes($a['id'], '"').'"';
				$diagram .= ",label=\"".addcslashes($a['name'], '"')."\"";
				if ($backwards) {
					$diagram .= ',constraint=0';
				}
				switch ($a['type']) {
					case 'sequenceFlow':
						$diagram .= 'style=solid,color="#666666",fontcolor="#aaaaaa"';
						$w = $backwards ? 3 : 5;
						break;
					case 'messageFlow':
						$diagram .= 'style=dashed,color="#666666",arrowhead=empty,arrowtail=odot';
						$w = 0;
						break;
					default: $diagram .= 'color=red'; break;
				}
				if ($primary_process_id && $a['process'] == $primary_process_id) {
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

				$diagram .= "\t\t" . $graph_id . " [tooltip=\"".addcslashes($n['id'], '"')."\"";
				switch ($n['type']) {
					case 'startEvent':
					case 'endEvent':
					case 'intermediateCatchEvent':
					case 'intermediateThrowEvent':
						if ($n['name'] != $id) {
							$diagram .= ",xlabel=\"".addcslashes($n['name'], '"')."\",fontcolor=\"#aaaaaa\"";
						}
						break;
					default:
						$diagram .= ",label=\"".addcslashes($n['name'], '"')."\"";
						break;
				}
				switch ($n['type']) {
					case 'participant': $diagram .= ',shape=box,style=filled,fillcolor="#ffffff",penwidth=2'; break;
					case 'startEvent': $diagram .= ',shape=circle,width=0.4,height=0.4,label="",root=1'; break;
					case 'intermediateCatchEvent': $diagram .= ',shape=doublecircle,width=0.35,label=""'; break;
					case 'intermediateThrowEvent': $diagram .= ',shape=doublecircle,width=0.35,label=""'; break;
					case 'endEvent': $diagram .= ',shape=circle,width=0.4,height=0.4,penwidth=3,label=""'; break;
					case 'exclusiveGateway': $diagram .= ',shape=diamond,style=filled,height=0.5,width=0.5,label="X"'; break;
					case 'parallelGateway': $diagram .= ',shape=diamond,style=filled,height=0.5,width=0.5,label="+"'; break;
					case 'inclusiveGateway': $diagram .= ',shape=diamond,style=filled,height=0.5,width=0.5,label="O"'; break;
					case 'complexGateway': $diagram .= ',shape=diamond,style=filled,height=0.5,width=0.5,label="*"'; break;
					case 'eventBasedGateway': $diagram .= ',shape=diamond,style=filled,height=0.5,width=0.5,label="E"'; break;
				}

				// Algorithm-specific nodes
				if (($primary_process_id && $n['process'] == $primary_process_id)
					|| ($state_machine_participant_id && $n['id'] == $state_machine_participant_id))
				{
					$diagram .= ',color="#44aa44",fillcolor="#eeffdd"';
				}
				if (isset($starting_nodes[$id]) || isset($ending_nodes[$id])) {
					$diagram .= ',fillcolor="#ffccaa"';
				}

				// Receiving/invoking background
				if (isset($invoking_actions[$id]) && isset($receiving_nodes[$id])) {
					$diagram .= ',fillcolor="#ffff88;0.5:#aaddff",gradientangle=270';
				} else if (isset($invoking_actions[$id])) {
					$diagram .= ',fillcolor="#ffff88"';
				} else if (isset($receiving_nodes[$id]) && !empty($used_receiving_nodes[$id])) {
					$diagram .= ',fillcolor="#aaddff"';
				}
				$diagram .= "];\n";
			}

			// Draw groups
			foreach ($fragment['groups'] as $id => $g) {
				$graph_id = AbstractMachine::exportDotIdentifier($id, $prefix);

				$diagram .= "\n\t\tsubgraph cluster_$graph_id {\n\t\t\tlabel= \"".basename($g['name'])."\"; color=\"#aaaaaa\";\n\n";
				foreach ($g['nodes'] as $n_id) {
					$graph_n_id = AbstractMachine::exportDotIdentifier($n_id, $prefix);
					$diagram .= "\t\t\t".$graph_n_id.";\n";
				}
				$diagram .= "\t\t}\n";
			}

			//-------------------------------------------

			// Draw $sm_next_node -- base for green arrows
			/*
			foreach ($sm_next_node as $src => $dst_list) {
				foreach($dst_list as $dst) {
					$source = AbstractMachine::exportDotIdentifier($src, $prefix);
					$target = AbstractMachine::exportDotIdentifier($dst, $prefix); 

					$diagram .= "\t\t" . $source . ' -> ' . $target. ' [constraint=0,splines=line,penwidth=3,style=dashed,color="#88dd6688"'
						. "]\n";
				}
			}
			// */

			// Draw $eq_states (green arrows)
			foreach ($eq_states as $src => $dst_list) {
				$source = AbstractMachine::exportDotIdentifier(preg_replace('/^Q[be]_/', '', $src), $prefix);
				foreach($dst_list as $dst) {
					$target = AbstractMachine::exportDotIdentifier(preg_replace('/^Q[be]_/', '', $dst), $prefix); 
					$diagram .= "\t\t" . $source . ' -> ' . $target. ' [constraint=0,splines=line,penwidth=5,color="#88dd6688"'
						//. ",label=\"$src\n$dst\",fontcolor=\"#44aa00\""
						. "]\n";
				}
			}

			// Draw $inv_rc_nodes (blue arrows)
			foreach ($inv_rc_nodes as $src => $dst_list) {
				foreach($dst_list as $dst_node) {
					$dst = $dst_node['id'];
					$source = AbstractMachine::exportDotIdentifier($src, $prefix);
					$target = AbstractMachine::exportDotIdentifier($dst, $prefix); 

					$diagram .= "\t\t" . $source . ' -> ' . $target. ' [constraint=0,splines=line,penwidth=5,color="#66aaff88"'
						//. ",label=\"$src\n$dst\",fontcolor=\"#0044aa\""
						. "]\n";
				}
			}

			//-------------------------------------------

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
			$preceding_states = [];
			foreach ($paths as $src => $dst_list) {
				$n = $fragment['nodes'][$src];
				if ($n['type'] == 'task') {
					$preceding_states[$src] = 'before_'.$n['name'];
				}
				if ($n['type'] == 'endEvent') {
					$preceding_states[$src] = '';
				}
			}

			// But if there is path between start event and action,
			// then action's preceding state is the start event.
			foreach ($paths as $src => $dst_list) {
				$n = $fragment['nodes'][$src];
				if ($n['type'] == 'startEvent') {
					foreach ($dst_list as $dst) {
						$preceding_states[$dst] = '';
					}
				}
			}

			// Register preceding states
			foreach ($preceding_states as $s) {
				if ($s != '') {
					$states[$s] = [
					];
				}
			}

			// So the actions start in the newly assigned states...
			foreach ($paths as $src => $dst_list) {
				if (!isset($preceding_states[$src])) {
					continue;
				}
				$src_state = $preceding_states[$src];
				$src_n = $fragment['nodes'][$src];
				$action_name = $src_n['name'];
				foreach ($dst_list as $dst) {
					$dst_state = $preceding_states[$dst];
					$actions[$action_name]['transitions'][$src_state]['targets'][] = $dst_state;
				}
			}
			//var_dump($actions);

			// Draw found paths
			$diagram .= "\tsubgraph cluster_".$prefix."_sm {\n\t\tlabel= \"Action paths\"; color=\"#44aa44\"; fillcolor=\"#ffffee\"; style=filled;\n\n";
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

