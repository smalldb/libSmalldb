<?php
/*
 * Copyright (c) 2016-2017, Josef Kufner  <josef@kufner.cz>
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

use Smalldb\StateMachine\Utils\UnionFind;
use Smalldb\StateMachine\Utils\Graph;
use Smalldb\StateMachine\Utils\GraphSearch;


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
 *   - `state_machine_participant`: ID of BPMN participant to implement by
 *     Smalldb state machine.
 *
 * @see https://camunda.org/bpmn/tool/
 */
class BpmnReader implements IMachineDefinitionReader
{

	/// @copydoc IMachineDefinitionReader::loadString
	public static function loadString($machine_type, $data_string, $options = [], $filename = null)
	{
		// Options
		$state_machine_participant_id = isset($options['state_machine_participant_id']) ? $options['state_machine_participant_id'] : null;

		// Load GraphML into DOM
		$dom = new \DOMDocument;
		$dom->loadXml($data_string);

		// Prepare XPath query engine
		$xpath = new \DOMXpath($dom);
		$xpath->registerNameSpace('bpmn', 'http://www.omg.org/spec/BPMN/20100524/MODEL');

		// Get participant
		if (!preg_match('/^[a-zA-Z0-9_.-]*$/', $state_machine_participant_id)) {
			throw new BpmnException('Invalid participant ID provided (only alphanumeric characters, underscore, dot and dash are allowed): '
				.var_export($state_machine_participant_id, true));
		}
		$participant_el = $xpath->query('/bpmn:definitions//bpmn:participant[@id=\''.$state_machine_participant_id.'\']')->item(0);
		if (!$participant_el) {
			throw new BpmnException('Participant representing the state machine not found: '.$state_machine_participant_id);
		}
		$state_machine_process_id = $participant_el->getAttribute('processRef') ? : '#';

		// Lets collect arrows, events and tasks (still in BPMN semantics)
		$arrows = [];
		$nodes = [];
		$groups = [];
		$participants = [];

		// Get processes (groups)
		foreach($xpath->query('//bpmn:process[@id]') as $el) {
			$id = trim($el->getAttribute('id'));
			$name = trim($el->getAttribute('name'));

			$groups[$id] = [
				'id' => $id,
				'name' => $name,
				'nodes' => [],
				'participant' => null,
				'_generated' => false,
			];
		}

		// Get participants and their processes
		foreach($xpath->query('//bpmn:participant[@id]') as $el) {
			$id = trim($el->getAttribute('id'));
			$name = trim($el->getAttribute('name'));
			$is_state_machine = ($id == $state_machine_participant_id);
			$process_id = $el->getAttribute('processRef') ? : ($is_state_machine ? '#' : 'x_'.$id.'_process');

			$participants[$id] = [
				'name' => $name,
				'process' => $process_id,
				'_state_machine' => $is_state_machine,
			];

			if (isset($groups[$process_id])) {
				$groups[$process_id]['participant'] = $id;
				if ($name != '') {
					$groups[$process_id]['name'] = $name;
				}
			} else {
				$groups[$process_id] = [
					'id' => $process_id,
					'name' => $name,
					'nodes' => [$id],
					'participant' => $id,
					'_generated' => true,
				];
			}
		}

		// Get arrows
		foreach (['sequenceFlow', 'messageFlow'] as $type) {
			foreach($xpath->query('//bpmn:'.$type.'[@id][@sourceRef][@targetRef]') as $el) {
				// Arrow properties
				$id = trim($el->getAttribute('id'));
				$name = trim($el->getAttribute('name'));
				$sourceRef = trim($el->getAttribute('sourceRef'));
				$targetRef = trim($el->getAttribute('targetRef'));

				// Store arrow
				$arrows[$id] = [
					'id' => $id,
					'type' => $type,
					'source' => $sourceRef,
					'target' => $targetRef,
					'name' => $name,
					'_transition' => false,
					'_state' => false,
					'_state_name' => null,
					'_generated' => false,
					'_dependency_only' => false,
				];
			}
		}

		// Get nodes
		foreach (['participant', 'startEvent', 'task', 'intermediateThrowEvent', 'intermediateCatchEvent', 'endEvent',
			'exclusiveGateway', 'parallelGateway', 'inclusiveGateway', 'complexGateway', 'eventBasedGateway',
			'textAnnotation'] as $type)
		{
			foreach($xpath->query('//bpmn:'.$type.'[@id]') as $el) {
				$id = trim($el->getAttribute('id'));
				$name = trim($el->getAttribute('name'));
				if ($name == '') {
					$name = $id;
				}

				// Get process where the node belongs
				if (($processRef = trim($el->getAttribute('processRef')))) {
					$process_id = $processRef;
				} else if (($process_element = $el->parentNode) && $process_element->tagName == 'bpmn:process') {
					$process_id = trim($process_element->getAttribute('id'));
				} else if ($type == 'participant' && isset($participants[$id])) {
					$process_id = $participants[$id]['process'];
				} else {
					$process_id = null;
				}
				if ($process_id) {
					$groups[$process_id]['nodes'][] = $id;
				}

				// Detect special features of intermediateCatchEvent and similar nodes
				$features = [];
				if ($xpath->evaluate('count(./bpmn:timerEventDefinition)', $el)) {
					$features['timerEventDefinition'] = true;
				}
				if ($xpath->evaluate('count(./bpmn:messageEventDefinition)', $el)) {
					$features['messageEventDefinition'] = true;
				}

				$nodes[$id] = [
					'id' => $id,
					'name' => $name,
					'type' => $type,
					'process' => $process_id,
					'features' => $features,
					'_distance' => null,
					'_invoking' => false,
					'_receiving' => false,
					'_receiving_nodes' => null,
					'_action_name' => null,
					'_transition' => null,
					'_state' => null,
					'_state_name' => null,
					'_generated' => false,
				];

				if ($type == 'textAnnotation') {
					$nodes[$id]['text'] = trim($el->nodeValue);
				} else {
					$nodes[$id]['annotations'] = [];
				}
			}
		}

		// Get annotations' associations
		foreach($xpath->query('//bpmn:association[@id]') as $el) {
			$source = trim($el->getAttribute('sourceRef'));
			$target = trim($el->getAttribute('targetRef'));

			if (!isset($nodes[$source]) || !isset($nodes[$target])) {
				continue;
			}

			if ($nodes[$source]['type'] == 'textAnnotation' && $nodes[$target]['type'] != 'textAnnotation') {
				$nodes[$source]['associations'][] = $target;
				$nodes[$target]['annotations'][] = $source;
			}
			if ($nodes[$target]['type'] == 'textAnnotation' && $nodes[$source]['type'] != 'textAnnotation') {
				$nodes[$target]['associations'][] = $source;
				$nodes[$source]['annotations'][] = $target;
			}
		}

		// Dump BPMN fragment (debug)
		/*
		printf("\n<hr><pre>BPMN diagram: <b>%s</b> (participant: <b>%s</b>, process: <b>%s</b>)\n",
				htmlspecialchars(basename($filename)), htmlspecialchars($state_machine_participant_id), htmlspecialchars($state_machine_process_id));
		printf("\n  Participants:\n");
		foreach ($participants as $id => $p) {
			printf("    %s (%s)\n", var_export($p['name'], true), var_export($id, true));
		}
		printf("\n  Groups:\n");
		foreach ($groups as $id => $g) {
			printf("    %s (%s)\n", var_export($g['name'], true), var_export($id, true));
		}
		printf("\n  Nodes:\n");
		foreach ($nodes as $id => $n) {
			printf("    %s (%s)\n", var_export($n['name'], true), var_export($id, true));
			if (isset($n['annotations'])) {
				foreach ($n['annotations'] as $ann) {
					printf("        Ann: [%s] %s\n", $ann, trim($nodes[$ann]['text']));
				}
			}
		}
		printf("\n  Arrows:\n");
		foreach ($arrows as $id => $a) {
			printf("    %40s ---%'--12s---> %-40s (%s, %s)\n", $a['source'], $a['type'], $a['target'], var_export($id, true), var_export($a['name'], true));
		}
		printf("</pre><hr>\n");
		// */

		// Store fragment in state machine definition
		return [
			'bpmn_fragments' => [
				$filename.'#'.$state_machine_participant_id => [
					'file' => $filename,
					'state_machine_participant_id' => $state_machine_participant_id,
					'state_machine_process_id' => $state_machine_process_id,
					'arrows' => $arrows,
					'nodes' => $nodes,
					'groups' => $groups,
					'participants' => $participants,
				],
			],
		];

	}


	/// @copydoc IMachineDefinitionReader::postprocessDefinition
	public static function postprocessDefinition($machine_type, & $machine_def, & $errors)
	{
		if (!isset($machine_def['bpmn_fragments'])) {
			return;
		}
		$bpmn_fragments = $machine_def['bpmn_fragments'];
		unset($machine_def['bpmn_fragments']);

		$states = [];
		$actions = [];
		$success = true;

		// Each included BPMN file provided one fragment
		foreach ($bpmn_fragments as $fragment_name => $fragment) {
			$prefix = "bpmn_".(0xffff & crc32($fragment_name)).'_';

			// Infer part of state machine from the BPMN fragment
			list($fragment_machine_def, $fragment_errors, $fragment_extra_vars) = static::inferStateMachine($prefix, $fragment_name, $fragment, $errors);

			// Update the definition
			if (empty($fragment_errors)) {
				$machine_def = array_replace_recursive($fragment_machine_def, $machine_def);
			} else {
				$success = false;
			}

			// Add BPMN diagram to state diagram
			$machine_def['state_diagram_extras'][] = static::renderBpmn($prefix, $fragment_name, $fragment, $fragment_errors, $fragment_extra_vars);
		}

		return $success;
	}


	protected static function inferStateMachine($prefix, $fragment_file, & $fragment, & $errors)
	{
		// Results
		$machine_def = [];

		// Shortcuts
		$state_machine_process_id = & $fragment['state_machine_process_id'];
		$state_machine_participant_id = & $fragment['state_machine_participant_id'];
		$groups = & $fragment['groups'];
		$arrows = & $fragment['arrows'];
		$nodes = & $fragment['nodes'];

		$g = new Graph($nodes, $arrows, ['_invoking', '_receiving'], [ '_transition', '_state' ]);

		// Find connections to state machine participant
		foreach ($arrows as $a_id => $a) {
			if ($a['type'] != 'messageFlow') {
				continue;
			}

			// Invoking message flow
			if ($nodes[$a['source']]['process'] != $state_machine_process_id 
				&& ($nodes[$a['target']]['process'] == $state_machine_process_id))
			{
				$g->tagNode($a['source'], '_invoking');
				if ($nodes[$a['source']]['_action_name'] !== null && $nodes[$a['source']]['_action_name'] != $a['target']) {
					$errors[] = [ 'text' => 'Multiple actions invoked by a single task.', 'nodes' => [$a['source']]];
				} else {
					$nodes[$a['source']]['_action_name'] = $a['id'];
				}
			}

			// Receiving message flow
			if ($nodes[$a['target']]['process'] != $state_machine_process_id 
				&& ($nodes[$a['source']]['process'] == $state_machine_process_id))
			{
				$g->tagNode($a['target'], '_receiving');
				if ($nodes[$a['target']]['_action_name'] !== null && $nodes[$a['target']]['_action_name'] != $a['source']) {
					$errors[] = [ 'text' => 'Multiple actions invoked by a single task.', 'nodes' => [$a['target']]];
				} else {
					$nodes[$a['target']]['_action_name'] = $a['id'];
				}
			}
		}

		// Add implicit tasks to BPMN diagram - message flow targets
		foreach ($arrows as & $arrow) {
			if ($a['type'] != 'messageFlow') {
				continue;
			}

			if ($arrow['target'] == $state_machine_participant_id) {
				$new_node_id = 'x_' . $arrow['id'] . '_target';
				$nodes[$new_node_id] = [
					'id' => $new_node_id,
					'name' => $arrow['name'],
					'type' => 'task',
					'process' => $fragment['state_machine_process_id'],
					'features' => [],
					'_distance' => null,
					'_invoking' => false,
					'_receiving' => false,
					'_receiving_nodes' => null,
					'_action_name' => null,
					'_transition' => null,
					'_state' => null,
					'_generated' => true,
				];
				$groups[$state_machine_process_id]['nodes'][] = $new_node_id;
				$arrow['target'] = $new_node_id;
			}
		}
		unset($arrow);
		$g->recalculateGraph();

		// Timers events are also invoking nodes (kind of)
		/*
		foreach ($nodes as $node) {
			if ($node['type'] == 'intermediateCatchEvent' && isset($node['features']['timerEventDefinition'])) {
				$g->tagNode($node, '_invoking');
			}
		}
		// */

		// Find receiving nodes for each invoking node
		// (DFS to next task or waiting, the receiver cannot be further than that)
		foreach ($g->getNodesByTag('_invoking') as $in_id => & $invoking_node) {
			$nodes[$invoking_node['id']]['_receiving_nodes'] = [];
			$inv_process = $invoking_node['process'];
			$receiving_nodes = [];
			$visited_arrows = [];
			$visited_nodes = [];

			GraphSearch::DFS($g)
				->onArrow(function(& $cur_node, & $arrow, & $next_node, $seen) use ($g, $inv_process, & $receiving_nodes, & $visited_arrows, & $visited_nodes) {
					// The receiving node must be within the same process
					if ($next_node['process'] != $inv_process) {
						return false;
					}

					// Don't cross invoking nodes
					if ($next_node['_invoking']) {
						return false;
					}

					// If receiving node is found, we are done
					if ($next_node['_receiving']) {
						$receiving_nodes[] = $next_node['id'];
						$visited_arrows[] = $arrow['id'];
						return false;
					}

					// Don't cross intermediate events
					if ($next_node['type'] == 'intermediateCatchEvent' || $next_node['type'] == 'endEvent') {
						return false;
					}

					// Otherwise continue search
					$visited_arrows[] = $arrow['id'];
					$visited_nodes[] = $next_node['id'];
					return true;
				})
				->start([$invoking_node]);

			// There should be only one message flow arrow to the state machine.
			$inv_arrow = null;
			foreach ($g->getArrowsByNode($invoking_node) as $i => $a) {
				if ($a['type'] != 'messageFlow') {
					continue;
				}
				if ($nodes[$a['target']]['process'] == $state_machine_process_id) {
					if (isset($inv_arrow)) {
						$errors[] = [
							'text' => 'Multiple invoking arrows.',
							'nodes' => [ $invoking_node ],
						];
						break;
					} else {
						$inv_arrow = $a;
					}
				}
			}

			if (empty($receiving_nodes)) {
				// If there is no receiving node, add implicit returning message flow.
				if ($inv_arrow) {
					// Add receiving arrow only if there is invoking arrow
					// (timer events may represent transitions without invoking arrow).
					$new_id = 'x_'.$in_id.'_receiving';
					$arrows[$new_id] = [
						'id' => $new_id,
						'type' => 'messageFlow',
						'source' => $inv_arrow['target'],
						'target' => $invoking_node['id'],
						'name' => $inv_arrow['name'],
						'_transition' => false,
						'_state' => false,
						'_generated' => true,
					];
				}
				$g->tagNode($invoking_node, '_receiving');
				$invoking_node['_receiving_nodes'][] = $invoking_node['id'];
			} else {
				// If there are receiving nodes, make sure the arrows start from task, not from participant.
				foreach ($receiving_nodes as $ri => $rcv_node) {
					$rcv_arrow = null;
					foreach ($g->getArrowsByTargetNode($rcv_node) as $i => & $a) {
						if ($a['type'] != 'messageFlow') {
							continue;
						}
						if (isset($rcv_arrow)) {
							$errors[] = [
								'text' => 'Multiple receiving arrows.',
								'nodes' => [ $rcv_node ],
							];
							break;
						} else {
							$rcv_arrow = & $a;
						}
					}

					if ($rcv_arrow && $rcv_arrow['source'] == $state_machine_participant_id) {
						$rcv_arrow['source'] = $inv_arrow['target'];
					}

					$invoking_node['_receiving_nodes'][] = $rcv_node;

					unset($a);
					unset($rcv_arrow);
				}

				// Mark visited arrows as belonging to a transition (blue arrows)
				foreach ($visited_arrows as $id) {
					$g->tagArrow($id, '_transition');
				}

				// Mark visited nodes as part of the transition
				foreach ($visited_nodes as $id) {
					$g->tagNode($id, '_transition');
				}
			}
		}
		unset($invoking_node);
		$g->recalculateGraph();

		// Remove receiving tag from nodes without action
		$active_receiving_nodes = [];
		foreach ($g->getNodesByTag('_invoking') as $id => $node) {
			foreach ($node['_receiving_nodes'] as $rcv_node_id) {
				$active_receiving_nodes[$rcv_node_id] = true;
			}
		}
		foreach ($g->getNodesByTag('_receiving') as $id => $node) {
			if (empty($active_receiving_nodes[$id])) {
				$g->tagNode($id, '_receiving', false);
			}
		}

		// Detect states - components separed by transitions
		GraphSearch::DFS($g)
			->onArrow(function(& $cur_node, & $arrow, & $next_node, $seen) use ($g) {
				if ($arrow['_transition'] || $arrow['type'] != 'sequenceFlow' || $next_node['process'] != $cur_node['process']) {
					return false;
				}
				$g->tagArrow($arrow, '_state');
				if ($next_node['_invoking'] || $next_node['type'] == 'endEvent') {
					return false;
				}
				$g->tagNode($next_node, '_state');
				return true;
			})
			->start(array_merge($g->getNodesByTag('_receiving'), $g->getNodesByType('startEvent')));

		// Merge green arrows and nodes into states
		$uf = new UnionFind();
		foreach ($nodes as $id => $node) {
			if ($node['_invoking']) {
				$uf->add('Qin_'.$id);
			}
			if ($node['_receiving']) {
				$uf->add('Qout_'.$id);
			}
		}
		foreach ($arrows as $id => $arrow) {
			if ($arrow['_state']) {
				// Add entry and exit points
				$uf->add('Qout_'.$arrow['source']);
				$uf->add('Qin_'.$arrow['target']);
				$uf->union('Qout_'.$arrow['source'], 'Qin_'.$arrow['target']);

				// Add the arrow itself, so we can find to which state it belongs
				$uf->addUnique($id);
				$uf->union($id, 'Qin_'.$arrow['target']);
			}
		}
		foreach ($nodes as $id => $node) {
			if ($node['_state']) {
				// Connect input with output as this node is pass-through
				$uf->add('Qout_'.$id);
				$uf->add('Qin_'.$id);
				$uf->union('Qin_'.$id, 'Qout_'.$id);

				// Add the node itself, so we can find to which state it belongs
				$uf->addUnique($id);
				$uf->union($id, 'Qin_'.$id);
			}
		}

		// All message flows from a single task in the state machine process end in the same state
		foreach ($nodes as $id => $node) {
			if ($node['process'] == $state_machine_process_id && $node['type'] == 'task') {
				// Collect nodes to which message flows flow
				$receiving_nodes = [];
				$targets = [];
				$state_arrows = [];
				foreach ($g->getArrowsByNode($node) as $a_id => $arrow) {
					if ($arrow['type'] == 'messageFlow') {
						if ($nodes[$arrow['target']]['_receiving']) {
							// There should be one receiving node ...
							$receiving_nodes[] = $arrow['target'];
						} else {
							// ... and multiple other targets
							$targets[] = $arrow['target'];
						}
						$state_arrows[] = $a_id;
					}
				}

				if (!empty($targets) && count($receiving_nodes) == 1) {
					// If there are targets, define the state equivalence
					$rcv = reset($receiving_nodes);
					$uf->add('Qout_'.$rcv);
					foreach ($targets as $t) {
						$uf->add('Qout_'.$t);
						$uf->union('Qout_'.$rcv, 'Qout_'.$t);

						// Assign state to the target node
						$g->tagNode($t, '_state');
						$uf->add($t);
						$uf->union('Qout_'.$t, $t);
					}
					foreach ($state_arrows as $a_id) {
						// Assign state to the arrow
						$g->tagArrow($a_id, '_state');
						$uf->add($a_id);
						$uf->union('Qout_'.$rcv, $a_id);
					}
				}
			}
		}

		// Detect state machine annotation symbol
		if (preg_match('/^\s*(@[^:\s]+)(|:\s*.+)$/', $fragment['nodes'][$state_machine_participant_id]['name'], $m)) {
			$state_machine_annotation_symbol = $m[1];
		} else {
			$state_machine_annotation_symbol = '@';
		}

		// Collect name states from annotations
		$custom_state_names = [];
		foreach ($nodes as $n_id => $node) {
			if ($node['type'] == 'participant' || $node['type'] == 'annotation') {
				continue;
			}

			// Collect annotation texts
			$texts = [];
			foreach (explode("\n", $node['name']) as $t) {
				$texts[] = trim($t);
			}
			if (!empty($node['annotations'])) {
				foreach ($node['annotations'] as $ann_id) {
					foreach (explode("\n", $nodes[$ann_id]['text']) as $t) {
						$texts[] = trim($t);
					}
				}
			}

			// Parse annotation texts
			$ann_state_names = [];
			foreach ($texts as $t) {
				$custom_state_name = null;

				if ($state_machine_annotation_symbol == '@') {
					if (preg_match('/^@([^\s]+)$/', $t, $m)) {
						$custom_state_name = $m[1];
					}
				} else {
					if (preg_match('/^\s*(@[^:\s]+)[: ]\s*(.+)$/', $t, $m) && $m[1] == $state_machine_annotation_symbol) {
						$custom_state_name = $m[2];
					}
				}

				if ($custom_state_name !== null) {
					$ann_state_names[] = ($custom_state_name == '-' ? '' : $custom_state_name);
				}
			}

			// Check if there is only one state specified
			$c = count($ann_state_names);
			if ($c == 1) {
				$custom_state_names[reset($ann_state_names)][] = $n_id;
			} else if ($c > 1) {
				throw new BpmnAnnotationException('Annotations define multiple names for a single state (found when searching): '
					.join(', ', $ann_state_names));
			}
		}

		// Assign state names to states (UnionFind will use them as they are added last)
		foreach ($custom_state_names as $state => $node_ids) {
			$uf->addUnique($state);
			foreach ($node_ids as $node_id) {
				$node = $nodes[$node_id];
				if ($node['_state'] || $node['_receiving'] || $node['type'] == 'startEvent') {
					$uf->union($state, 'Qout_'.$node_id);
				} else if ($node['_invoking'] || $node['type'] == 'endEvent') {
					$uf->union($state, 'Qin_'.$node_id);
				} else {
					$errors[] = ['text' => 'Unused annotation.', 'nodes' => [$node_id]];
				}
			}
		}

		// Add implicit '' for start states
		foreach ($g->getNodesByType('startEvent') as $s_id => $s_n) {
			$s = 'Qout_'.$s_id;
			$uf->add($s);
			if (!isset($custom_state_names[$uf->find($s)])) {
				$uf->add('');
				$uf->union('', $s);
			}
		}

		// Add implicit '' for final states
		foreach ($g->getNodesByType('endEvent') as $e_id => $e_n) {
			$s = 'Qin_'.$e_id;
			$uf->add($s);
			if (!isset($custom_state_names[$uf->find($s)])) {
				$uf->add('');
				$uf->union('', $s);
			}
		}

		// Check that two custom states are not merged into one
		foreach ($custom_state_names as $a => $na) {
			foreach ($custom_state_names as $b => $nb) {
				if ($a !== $b && $uf->find($a) === $uf->find($b)) {
					$n = array_merge($na, $nb);
					sort($n);
					$errors[] = [
						'text' => 'Annotations define multiple names for a single state (found when merging): '.join(', ', [$a, $b]),
						'nodes' => $n,
					];
					break 2;
				}
			}
		}

		// Create states from merged green arrows
		$states = [];
		foreach ($uf->findDistinct() as $id) {
			$states[$id] = [];
		}

		if (!empty($errors)) {
			return [ $machine_def, $errors, [] ];
		}

		// Find all transitions
		$actions = [];
		foreach ($g->getNodesByTag('_invoking') as $id => $node) {
			if (empty($node['_action_name'])) {
				// Skip invoking nodes without action
				continue;
			}
			// Get action
			$a_arrow = $g->getArrow($node['_action_name']);
			$a_node = $g->getNode($a_arrow['target']);
			$action = $a_node['name'];

			// Define transition
			$state_before = $uf->find('Qin_'.$id);
			foreach($node['_receiving_nodes'] as $rcv_id) {
				$state_after = $uf->find('Qout_'.$rcv_id);
				$actions[$action]['transitions'][$state_before]['targets'][] = $state_after;
			}
		}

		// At this point the state machine is complete, so lets assign states and transitions to BPMN nodes.
		foreach ($nodes as $id => & $node) {
			if ($node['_state']) {
				$node['_state_name'] = $uf->find($id);
			}
		}
		unset($node);
		foreach ($arrows as $id => & $arrow) {
			if ($arrow['_state']) {
				$arrow['_state_name'] = $uf->find($id);
			}
		}
		unset($arrow);

		// Calculate distance of each node from nearest start event 
		// to detect backward arrows and make diagrams look much better
		$distance = 0;
		GraphSearch::BFS($g)
			->onNode(function(& $cur_node) use (& $distance) {
				if (!isset($cur_node['_distance'])) {
					$cur_node['_distance'] = 0;
				}
				$distance = $cur_node['_distance'] + 1;
				return true;
			})
			->onArrow(function(& $cur_node, & $arrow, & $next_node, $seen) use (& $distance) {
				if ($arrow['type'] == 'sequenceFlow' && !$seen) {
					$next_node['_distance'] = $distance;
					return true;
				}
			})
			->start($g->getNodesByType('startEvent'));

		// Detect connections between generated nodes in state machine process to arrange them nicely
		// FIXME: This is wrong.
		foreach (array_filter($nodes, function($n) use ($state_machine_process_id) {
				return $n['type'] == 'task' && $n['process'] == $state_machine_process_id;
			}) as $start_node) {
			$start_arrows = $g->getArrowsByNode($start_node);
			$next_start_nodes = array_map(function($a) { return $a['target']; }, $start_arrows);
			$next_start_nodes_inv = array_flip($next_start_nodes);
			GraphSearch::DFS($g)
				->onArrow(function(& $cur_node, & $arrow, & $next_node, $seen) use ($g, & $arrows, $state_machine_process_id, $start_node, $next_start_nodes_inv) {
					// Ignore state machine's own arrows
					if ($cur_node['process'] == $state_machine_process_id && $next_node['process'] == $state_machine_process_id) {
						return false;
					}

					// Don't pass through invoking nodes, except messageFlow
					if (!isset($next_start_nodes_inv[$cur_node['id']]) && $cur_node['_invoking'] && $arrow['type'] == 'sequenceFlow') {
						return false;
					}

					// If we returned to state machine process
					if ($next_node['process'] == $state_machine_process_id && $start_node['id'] != $next_node['id']) {
						// link the next node to starting node
						$new_id = 'x_'.$start_node['id'].'_'.$next_node['id'];
						$arrows[$new_id] = [
							'id' => $new_id,
							'type' => 'sequenceFlow',
							'source' => $start_node['id'],
							'target' => $next_node['id'],
							'name' => null,
							'_transition' => false,
							'_state' => false,
							'_generated' => true,
							'_dependency_only' => true,
						];
					}

					return true;
				})
				->start($next_start_nodes);
		}
		$g->recalculateGraph();

		// Merge '_state' (green) arrows into states.

		// Connect states using blue arrows

		$extra_vars = [];
		$machine_def['states'] = $states;
		$machine_def['actions'] = $actions;
		return [ $machine_def, $errors, $extra_vars ];
	}


	protected static function renderBpmn($prefix, $fragment_file, $fragment, $errors, $extra_vars)
	{
		$diagram = "\tsubgraph cluster_$prefix {\n\t\tlabel= \"BPMN:\\n".basename($fragment_file)."\"; color=\"#5373B4\";\n\n";

		// Draw arrows
		foreach ($fragment['arrows'] as $id => $a) {
			$source = AbstractMachine::exportDotIdentifier($a['source'], $prefix);
			$target = AbstractMachine::exportDotIdentifier($a['target'], $prefix); 
			if (!$a['source'] || !$a['target']) {
				var_dump($id);
				var_dump($a);
			}
			$backwards = ($fragment['nodes'][$a['source']]['_distance'] >= $fragment['nodes'][$a['target']]['_distance'])
					&& $fragment['nodes'][$a['target']]['_distance'];
			$label = $a['name'];
			if ($a['_generated'] && $label != '') {
				$label = "($label)";
			}
			$tooltip = json_encode($a, JSON_NUMERIC_CHECK|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

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
				default: $color = '#ff0000'; break;
			}

			$diagram .= ",color=\"$color$alpha\",fontcolor=\"#aaaaaa$alpha\"";
			$diagram .= ",weight=$w";
			$diagram .= "];\n";

			$nodes[$source] = $a['source'];
			$nodes[$target] = $a['target'];
		}

		$diagram .= "\n";

		// Draw nodes
		$hidden_nodes = [];
		foreach ($fragment['nodes'] as $id => $n) {
			$graph_id = AbstractMachine::exportDotIdentifier($id, $prefix);

			// Skip unconnected participants
			if ($n['type'] == 'participant') {
				$has_connection = false;
				foreach ($fragment['arrows'] as $a) {
					if ($a['target'] == $n['id'] || $a['source'] == $n['id']) {
						$has_connection = true;
						break;
					}
				}
				if (!$has_connection) {
					$hidden_nodes[$id] = true;
					continue;
				}
			}

			// Render node
			$label = trim($n['name']);
			if ($n['_generated'] && $label != '') {
				$label = "($label)";
			}
			$alpha = ($n['_generated'] ? '66' : '');
			$tooltip = json_encode($n, JSON_NUMERIC_CHECK|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
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
			}

			// End of node.
			$diagram .= "];\n";

			// Draw annotation associations
			if (!empty($n['annotations'])) {
				foreach ($n['annotations'] as $ann_node_id) {
					$ann_graph_id = AbstractMachine::exportDotIdentifier($ann_node_id, $prefix);
					$diagram .= "\t\t" . $graph_id . " -> " . $ann_graph_id
						. " [id=\"" . addcslashes($prefix.$ann_node_id.'__line', "\"\n") . "\""
						.",style=dashed,color=\"#aaaaaa$alpha\",arrowhead=none];\n";
				}
			}
		}

		// Draw groups
		foreach ($fragment['groups'] as $id => $g) {
			$graph_id = AbstractMachine::exportDotIdentifier($id, $prefix);

			$diagram .= "\n\t\tsubgraph cluster_$graph_id {\n\t\t\tlabel= \"".basename($g['name'])."\"; color=\"#aaaaaa\";\n\n";

			if (($fragment['state_machine_process_id'] && $g['id'] == $fragment['state_machine_process_id'])
				|| ($fragment['state_machine_participant_id'] && $g['id'] == $fragment['state_machine_participant_id']))
			{
				$diagram .= "\t\tcolor=\"#aadd88\"; penwidth=5; fontcolor=\"#44aa00;\"\n";
			}
			foreach ($g['nodes'] as $n_id) {
				if (!empty($hidden_nodes[$n_id])) {
					continue;
				}
				$graph_n_id = AbstractMachine::exportDotIdentifier($n_id, $prefix);
				$diagram .= "\t\t\t".$graph_n_id.";\n";
			}
			$diagram .= "\t\t}\n";
		}

		// Render errors
		foreach ($errors as $err) {
			$m = AbstractMachine::exportDotIdentifier('error_'.md5($err['text']), $prefix);
			$diagram .= "\t\t\"$m\" [color=\"#ff0000\",fillcolor=\"#ffeeee\",label=\"".addcslashes($err['text'], "\n\"")."\"];\n";
			foreach ($err['nodes'] as $n) {
				$nn = AbstractMachine::exportDotIdentifier($n, $prefix);
				$diagram .= "\t\t$nn -> \"$m\":n [color=\"#ffaaaa\",style=dashed,arrowhead=none];\n";
			}
		}

		//echo "<pre>", $diagram, "</pre>";

		$diagram .= "\t}\n";

		return $diagram;
	}

}

