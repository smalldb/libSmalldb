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

use Smalldb\StateMachine\Graph\Edge;
use Smalldb\StateMachine\Graph\Graph;
use Smalldb\StateMachine\Graph\GraphExportGrafovatko;
use Smalldb\StateMachine\Graph\GraphSearch;
use Smalldb\StateMachine\Graph\MissingElementException;
use Smalldb\StateMachine\Graph\NestedGraph;
use Smalldb\StateMachine\Graph\Node;
use Smalldb\StateMachine\Utils\UnionFind;


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
	public $disableSvgFile = false;
	public $rewriteGraph = false;


	/// @copydoc IMachineDefinitionReader::isSupported
	public function isSupported(string $file_extension): bool
	{
		return $file_extension == '.bpmn';
	}


	/// @copydoc IMachineDefinitionReader::loadString
	public function loadString(string $machine_type, string $data_string, array $options = [], string $filename = null)
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
		$processes = [];
		$participants = [];
		$graph = new Graph();
		$graph->indexNodeAttr('type');
		$graph->indexEdgeAttr('type');

		/** @var Graph[] $processNestedGraphs */
		$processNestedGraphs = [];

		// Get participants and their processes
		foreach($xpath->query('//bpmn:participant[@id]') as $el) {
			/** @var \DomElement $el */
			$id = trim($el->getAttribute('id'));
			$name = trim($el->getAttribute('name'));
			$is_state_machine = ($id == $state_machine_participant_id);
			$process_id = $el->getAttribute('processRef');

			if ($process_id === "" && $is_state_machine) {
				$process_id = "#";
				$processes[$process_id] = [
					'id' => $process_id,
					'name' => $name,
					'participant' => $id,
					'nodes' => [$id],
					'_generated' => true,
				];
			}

			$participants[$id] = [
				'name' => $name,
				'process' => $process_id,
				'_state_machine' => $is_state_machine,
			];

			if (isset($processes[$process_id])) {
				$processes[$process_id]['participant'] = $id;
				if ($name != '') {
					$processes[$process_id]['name'] = $name;
				}
			} else {
				$processes[$process_id] = [
					'id' => $process_id,
					'name' => $name,
					'nodes' => [$id],
					'participant' => $id,
					'_generated' => true,
				];
			}

			$node = $graph->createNode($id, [
				'id' => $id,
				'name' => $name,
				'type' => 'participant',
				'process' => $process_id,
				'features' => [],
				'_is_state_machine' => $is_state_machine,
				'_generated' => false,
			]);

			if ($process_id) {
				$processNestedGraphs[$process_id] = $node->getNestedGraph();
			}
		}

		// Get nodes
		foreach (['startEvent', 'task', 'sendTask', 'receiveTask', 'userTask', 'serviceTask',
			'intermediateThrowEvent', 'intermediateCatchEvent', 'endEvent',
			'exclusiveGateway', 'parallelGateway', 'inclusiveGateway', 'complexGateway', 'eventBasedGateway',
			'textAnnotation'] as $type)
		{
			foreach($xpath->query('//bpmn:'.$type.'[@id]') as $el) {
				/** @var \DomElement $el */
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
				} else {
					$process_id = null;
				}
				if ($process_id) {
					$processes[$process_id]['nodes'][] = $id;
				}

				// Detect special features of intermediateCatchEvent and similar nodes
				$features = [];
				if ($xpath->evaluate('count(./bpmn:timerEventDefinition)', $el)) {
					$features['timerEventDefinition'] = true;
				}
				if ($xpath->evaluate('count(./bpmn:messageEventDefinition)', $el)) {
					$features['messageEventDefinition'] = true;
				}

				if (!isset($processNestedGraphs[$process_id])) {
					throw new RuntimeException("Process graph \"$process_id\" not found for node \"$id\".");
				}

				$nodeGraph = $processNestedGraphs[$process_id];
				$node = $nodeGraph->createNode($id, [
					'id' => $id,
					'name' => $name,
					'type' => $type,
					'process' => $process_id,
					'features' => $features,
					'_generated' => false,
				]);

				if ($type == 'textAnnotation') {
					$node->setAttr('text', trim($el->nodeValue));
				} else {
					$node->setAttr('annotations', []);
				}
			}
		}

		// Get arrows
		foreach (['sequenceFlow', 'messageFlow'] as $type) {
			foreach($xpath->query('//bpmn:'.$type.'[@id][@sourceRef][@targetRef]') as $el) {
				/** @var \DomElement $el */

				// Arrow properties
				$id = trim($el->getAttribute('id'));
				$name = trim($el->getAttribute('name'));
				$sourceRef = trim($el->getAttribute('sourceRef'));
				$targetRef = trim($el->getAttribute('targetRef'));

				$source = $graph->getNodeById($sourceRef);
				$target = $graph->getNodeById($targetRef);

				$sourceGraph = $source->getGraph();
				$targetGraph = $target->getGraph();

				$edgeGraph = ($sourceGraph === $targetGraph ? $sourceGraph : $graph);

				// Store arrow
				$edgeGraph->createEdge($id, $source, $target, [
					'id' => $id,
					'type' => $type,
					'name' => $name,
				]);
			}
		}

		// Get annotations' associations
		foreach($xpath->query('//bpmn:association[@id]') as $el) {
			/** @var \DomElement $el */
			try {
				$source = $graph->getNodeById(trim($el->getAttribute('sourceRef')));
				$target = $graph->getNodeById(trim($el->getAttribute('targetRef')));
			}
			catch (MissingElementException $ex) {
				continue;
			}

			$sourceType = $source->getAttr('type');
			$targetType = $target->getAttr('type');

			$edgeGraph = ($source->getGraph() === $target->getGraph() ? $source->getGraph() : $graph);

			if ($sourceType == 'textAnnotation' && $targetType != 'textAnnotation') {
				$source['associations'][$target->getId()] = $target;
				$target['annotations'][$source->getId()] = $source;

				$edgeGraph->createEdge(null, $target, $source, [
					'type' => 'association',
				]);
			}
			if ($targetType == 'textAnnotation' && $sourceType != 'textAnnotation') {
				$target['associations'][$source->getId()] = $source;
				$source['annotations'][$target->getId()] = $target;

				$edgeGraph->createEdge(null, $source, $target, [
					'type' => 'association',
				]);
			}
		}

		// Load SVG file with rendered BPMN diagram, so we can colorize it
		if (!$this->disableSvgFile && isset($options['svg_file'])) {
			$dir = dirname($filename);
			$svg_file_name = ($dir == "" ? "./" : $dir . "/") . $options['svg_file'];
			$svg_file_contents = file_get_contents($svg_file_name);
			$svg_file_is_obsolete = (filemtime($filename) > filemtime($svg_file_name));
		} else {
			$svg_file_name = null;
			$svg_file_contents = null;
			$svg_file_is_obsolete = null;
		}

		// Store fragment in state machine definition
		return [
			'bpmn_fragments' => [
				$filename.'#'.$state_machine_participant_id => [
					'file' => $filename,
					'state_machine_participant_id' => $state_machine_participant_id,
					'state_machine_process_id' => $state_machine_process_id,
					'graph' => $graph,
					'participants' => $participants,
					'svg_file_name' => $svg_file_name,
					'svg_file_contents' => $svg_file_contents,
					'svg_file_is_obsolete' => $svg_file_is_obsolete,
				],
			],
		];

	}


	/// @copydoc IMachineDefinitionReader::postprocessDefinition
	public function postprocessDefinition(string $machine_type, array & $machine_def, array & $errors)
	{
		if (!isset($machine_def['bpmn_fragments'])) {
			return true;
		}
		$bpmn_fragments = $machine_def['bpmn_fragments'];
		unset($machine_def['bpmn_fragments']);

		$success = true;

		// Each included BPMN file provided one fragment
		foreach ($bpmn_fragments as $fragment_name => $fragment) {
			// Infer part of state machine from the BPMN fragment
			list($fragment_machine_def, $fragment_errors) = $this->inferStateMachine($fragment['graph'],
					$fragment['state_machine_participant_id'], $fragment['state_machine_process_id']);

			// Update the definition
			if (empty($fragment_errors)) {
				$machine_def = array_replace_recursive($fragment_machine_def, $machine_def);
			} else {
				$success = false;
			}

			// Add BPMN diagram to state diagram
			$prefix = "bpmn_".(0xffff & crc32($fragment_name)).'_';
			$machine_def['state_diagram_extras'][] = $this->renderBpmn($prefix, $fragment_name, $fragment, $fragment_errors);
			$this->renderBpmnJson($machine_def, $prefix, $fragment_name, $fragment, $fragment_errors);
		}

		return $success;
	}


	/**
	 * Add error to the graph
	 *
	 * @param array $errors
	 * @param string $message
	 * @param Node[] $nodes
	 */
	private function addError(array & $errors, string $message, array $nodes)
	{
		$errors[] = ['text' => $message, 'nodes' => $nodes];

		$errorGraph = null;
		foreach ($nodes as $node) {
			if ($errorGraph === null) {
				$errorGraph = $node->getGraph();
			} else {
				$nodeGraph = $node->getGraph();
				if ($nodeGraph !== $errorGraph) {
					$errorGraph = $node->getRootGraph();
					break;
				}
			}
		}

		$errorNodeId = '_error_'.md5($message).'_'.count($errors);
		$errorNode = $errorGraph->createNode($errorNodeId, ['label' => $message, 'type' => 'error']);
		foreach ($nodes as $node) {
			$errorGraph->createEdge(null, $errorNode, $node, ['type' => 'error']);
		}
	}


	protected function inferStateMachine(Graph $graph, string $state_machine_participant_id, string $state_machine_process_id)
	{
		$errors = [];

		// Add few more indices
		$graph->indexNodeAttr('_invoking');
		$graph->indexNodeAttr('_receiving');
		$graph->indexNodeAttr('_potential_receiving');

		// Stage 1: Add implicit tasks to BPMN diagram -- invoking message flow targets
		if ($this->rewriteGraph) {
			foreach ($graph->getAllEdges() as $edge) {
				if ($edge['type'] != 'messageFlow') {
					continue;
				}

				if ($edge->getEnd()->getId() == $state_machine_participant_id) {
					$state_machine_graph = $edge->getEnd()->getNestedGraph();
					$new_node_id = 'x_' . $edge->getId() . '_target';
					$new_node = $state_machine_graph->createNode($new_node_id, [
						'id' => $new_node_id,
						'name' => $edge['name'],
						'type' => 'task',
						'process' => $state_machine_process_id,
						'features' => [],
						'_generated' => true,
					]);
					$edge->setEnd($new_node);
				}
			}
		}

		// Stage 1: Find message flows to state machine participant, identify
		// invoking and potential receiving nodes
		foreach ($graph->getAllEdges() as $edgeId => $a) {
			if ($a['type'] != 'messageFlow') {
				continue;
			}

			$source = $a->getStart();
			$target = $a->getEnd();

			// Invoking message flow
			if ($source['process'] != $state_machine_process_id && ($target['process'] == $state_machine_process_id)) {
				$source->setAttr('_invoking', true);
				if ($source['_action_name'] !== null && $source['_action_name'] != $target->getId()) {
					$this->addError($errors, 'Multiple actions invoked by a single task.', [$source]);
				} else if (!$target['_is_state_machine']) {
					$source['_action_name'] = $target->getAttr('name');
				} else {
					$source['_action_name'] = $a->getAttr('name');
				}
			}

			// Receiving message flow
			if ($target['process'] != $state_machine_process_id && ($source['process'] == $state_machine_process_id)) {
				$target->setAttr('_receiving', true);
				$target->setAttr('_potential_receiving', true);
			}
		}

		// Stage 1: Find receiving nodes for each invoking node
		// (DFS to next task or event, the receiver cannot be further than that)
		foreach ($graph->getNodesByAttr('_invoking') as $in_id => $invoking_node) {
			$invoking_node->setAttr('_receiving_nodes', []);
			$invoking_process = $invoking_node['process'];
			/** @var Node[] $receiving_nodes */
			$receiving_nodes = [];
			/** @var Edge[] $visited_arrows */
			$visited_arrows = [];
			/** @var Node[] $visited_nodes */
			$visited_nodes = [];

			GraphSearch::DFS($graph)
				->onEdge(function(Node $cur_node, Edge $edge, Node $next_node, bool $seen) use ($graph, $invoking_process, & $receiving_nodes, & $visited_arrows, & $visited_nodes) {
					// The receiving node must be within the same process
					if ($next_node['process'] != $invoking_process) {
						return false;
					}

					// Don't cross invoking nodes
					if ($next_node['_invoking']) {
						return false;
					}

					// If receiving node is found, we are done
					if ($next_node['_receiving']) {
						$receiving_nodes[] = $next_node;
						$visited_arrows[] = $edge;
						return false;
					}

					// Don't cross intermediate events
					if ($next_node['type'] == 'intermediateCatchEvent' || $next_node['type'] == 'endEvent') {
						return false;
					}

					// Otherwise continue search
					$visited_arrows[] = $edge;
					$visited_nodes[] = $next_node;
					return true;
				})
				->start([$invoking_node]);

			// There should be only one message flow arrow to the state machine.
			$invoking_arrow = null;
			foreach ($invoking_node->getConnectedEdges() as $e) {
				if ($e->getStart() === $invoking_node) {
					if ($e['type'] != 'messageFlow') {
						continue;
					}
					$target = $e->getEnd();
					if ($target['process'] == $state_machine_process_id) {
						if (isset($invoking_arrow)) {
							$this->addError($errors, 'Multiple invoking arrows.', [$invoking_node]);
							break;
						} else {
							$invoking_arrow = $e;
						}
					}
				}
			}

			if (empty($receiving_nodes)) {
				// If there is no receiving node, add implicit returning message flow.
				if ($this->rewriteGraph && $invoking_arrow) {
					// Add receiving arrow only if there is invoking arrow
					// (timer events may represent transitions without invoking arrow).
					$new_id = 'x_'.$in_id.'_receiving';
					$graph->createEdge($new_id, $invoking_arrow->getEnd(), $invoking_arrow->getStart(), [
						'id' => $new_id,
						'type' => 'messageFlow',
						'name' => $invoking_arrow['name'],
						'_generated' => true,
					]);
				}
				$invoking_node->setAttr('_receiving', true);
				$invoking_node->setAttr('_potential_receiving', true);
				$invoking_node['_receiving_nodes'][$invoking_node->getId()] = $invoking_node;
				$invoking_node['_invoking_node'] = $invoking_node;
			} else {
				// If there are receiving nodes, make sure the arrows start from task, not from participant.
				foreach ($receiving_nodes as $ri => $rcv_node) {
					$rcv_arrow = null;
					foreach ($rcv_node->getConnectedEdges() as $i => $a) {
						if ($a->getEnd() !== $rcv_node || $a['type'] != 'messageFlow') {
							continue;
						}
						if (isset($rcv_arrow)) {
							$this->addError($errors, 'Multiple receiving arrows.', [$rcv_node]);
							break;
						} else {
							$rcv_arrow = $a;
						}
					}

					if ($this->rewriteGraph && $rcv_arrow && $rcv_arrow->getStart()->getId() == $state_machine_participant_id) {
						$rcv_arrow->setStart($invoking_arrow->getEnd());
					}

					$invoking_node['_receiving_nodes'][$rcv_node->getId()] = $rcv_node;
					$rcv_node['_invoking_node'] = $invoking_node;
				}

				// (M_T) Mark visited arrows as belonging to a transition (blue arrows)
				foreach ($visited_arrows as $a) {
					$a->setAttr('_transition', true);
				}

				// (M_T) Mark visited nodes as part of the transition
				foreach ($visited_nodes as $n) {
					$n->setAttr('_transition', true);
				}
			}
		}

		// Stage 1: Remove receiving tag from nodes without action
		/** @var Node[] $active_receiving_nodes */
		$active_receiving_nodes = [];
		foreach ($graph->getNodesByAttr('_invoking', true) as $id => $node) {
			/** @var Node $node */
			foreach ($node['_receiving_nodes'] as $rcv_node) {
				/** @var Node $rcv_node */
				$active_receiving_nodes[$rcv_node->getId()] = $rcv_node;
			}
		}
		foreach ($graph->getNodesByAttr('_receiving', true) as $id => $node) {
			/** @var Node $node */
			if (empty($active_receiving_nodes[$id])) {
				$node->setAttr('_receiving', false);
			}
		}

		// Stage 3: Detect state machine annotation symbol
		$state_machine_participant_node = $graph->getNodeById($state_machine_participant_id);
		if (preg_match('/^\s*(@[^:\s]+)(|:\s*.+)$/', $state_machine_participant_node['name'], $m)) {
			$state_machine_annotation_symbol = $m[1];
		} else {
			$state_machine_annotation_symbol = '@';
		}

		// Stage 3: Collect name states from annotations
		$custom_state_names = [];
		foreach ($graph->getAllNodes() as $n_id => $node) {
			if ($node['type'] == 'participant' || $node['type'] == 'annotation' || $node['process'] == $state_machine_process_id) {
				continue;
			}

			// Collect annotation texts
			$texts = [];
			foreach (explode("\n", $node['name']) as $t) {
				$texts[] = trim($t);
			}
			if (!empty($node['annotations'])) {
				foreach ($node['annotations'] as $ann_id => $ann_node) {
					foreach (explode("\n", $ann_node['text']) as $t) {
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
				$annotation_state_name = reset($ann_state_names);
				$node['_annotation_state'] = $annotation_state_name;
				$custom_state_names[$annotation_state_name][] = $n_id;
			} else if ($c > 1) {
				throw new BpmnAnnotationException('Annotations define multiple names for a single state (found when searching): '
					.join(', ', $ann_state_names));
			}
		}

		// Stage 2: State relation
		foreach (array_merge($graph->getNodesByAttr('type', 'startEvent'), $graph->getNodesByAttr('_potential_receiving'))  as $receiving_node_id => $receiving_node) {
			/** @var Node $receiving_node */
			/** @var Node[] $next_invoking_nodes */
			$next_invoking_nodes = [];
			$next_annotations = [];

			GraphSearch::DFS($graph)
				->onEdge(function(Node $cur_node, Edge $edge, Node $next_node, bool $seen)
					use ($graph, $state_machine_process_id, $receiving_node_id, $receiving_node, & $next_invoking_nodes)
				{
					// Don't follow message flows. Don't enter state machine participant.
					if ($edge['type'] == 'messageFlow' || $next_node['process'] == $state_machine_process_id) {
						return false;
					}

					$edge['_state_from'][$receiving_node_id] = false;
					$next_node['_state_from'][$receiving_node_id] = false;

					if ($next_node['_invoking'] || $next_node['_potential_receiving'] || $next_node['type'] == 'endEvent') {
						// Found a target
						$next_invoking_nodes[$next_node->getId()] = $next_node;
						return false;
					}

					return true;
				})
				->start([$receiving_node]);

			// We know all invoking nodes following the receiving node
			$receiving_node->setAttr('_next_invoking_nodes', $next_invoking_nodes);

			GraphSearch::DFS($graph)
				->runBackward()
				->onEdge(function(Node $cur_node, Edge $edge, Node $next_node, bool $seen)
					use ($graph, $receiving_node_id, $receiving_node, & $next_invoking_nodes, & $next_annotations)
				{
					if (!isset($edge['_state_from'][$receiving_node_id]) || $edge['_state_from'][$receiving_node_id] !== false) {
						// Visit only what we visited on the forward run
						return false;
					}

					$edge['_state_from'][$receiving_node_id] = true;
					$cur_node['_state_from'][$receiving_node_id] = true;

					// Mark node and edge as part of the state
					if (!$cur_node['_potential_receiving'] && !$cur_node['_invoking']) {
						$cur_node['_state'] = true;
					}
					$edge['_state'] = true;

					// Collect annotations on all paths from R+ to I
					if (isset($cur_node['_annotation_state']) && !$cur_node['_potential_receiving'] && !$cur_node['_invoking']) {
						$next_annotations[$cur_node['_annotation_state']] = true;
					}
					if (isset($next_node['_annotation_state'])) {
						$next_annotations[$next_node['_annotation_state']] = true;
					}

					return true;
				})
				->start($next_invoking_nodes);

			// Store reachable annotations and propagate the annotations
			$next_annotations_attr = array_keys($next_annotations);
			$receiving_node->setAttr('_next_annotations', $next_annotations_attr);
			if (count($next_annotations) > 1) {
				$this->addError($errors, 'Multiple annotations: ' . join(', ', $next_annotations_attr), [$receiving_node]);
			}
		}

		// Implicit state propagation
		foreach ($graph->getNodesByAttr('_receiving') as $id => $receiving_node) {
			// Get invoking node as the returning message flow may be implicit (and not drawn)
			/** @var Node $invoking_node */
			$invoking_node = $receiving_node['_invoking_node'];

			// Find task node (there should be a single incoming message flow)
			$task_node = null;
			foreach ($invoking_node->getConnectedEdges() as $edge) {
				if ($edge->getAttr('type') == 'messageFlow' && $edge->getStart() === $invoking_node
					&& $edge->getEnd()->getAttr('process') == $state_machine_process_id)
				{
					if ($task_node === null) {
						// Found the first incoming message flow
						$task_node = $edge->getEnd();
					} else {
						// Found the second incoming message flow -- do not propagate the state
						$task_node = null;
						break;
					}
				}
			}
			if (!$task_node) {
				continue;
			}

			// Get list of connected potential receiving nodes
			$potential_receiving_nodes = [];
			$receiving_node_count = 0;
			$invoking_process = $invoking_node->getAttr('process');
			foreach ($task_node->getConnectedEdges() as $edge) {
				$end = $edge->getEnd();
				if ($edge->getAttr('type') == 'messageFlow') {
					if ($end->getAttr('_receiving')) {
						if ($end->getAttr('process') == $invoking_process) {
							// Found a receiving node
							$receiving_node_count++;
						} else {
							// Receiving node in another process? WTF?
							$this->addError($errors, 'Inconsistent receiving nodes (algorithm bug).', [$end]);
							$potential_receiving_nodes = [];
							break;
						}
					} else if ($end->getAttr('_potential_receiving')) {
						if (empty($end->getAttr('_next_annotations'))) {
							// The node will receive state propagation
							$potential_receiving_nodes[$end->getId()] = $end;
						} else {
							// Stop if there are annotations already
							$potential_receiving_nodes = [];
							break;
						}
					}
				}
			}

			// Propagate the state if there is at most one connected receiving node (the one from which we arrived)
			if ($receiving_node_count <= 1 && !empty($potential_receiving_nodes)) {
				$next_annotations = $receiving_node->getAttr('_next_annotations');
				foreach ($potential_receiving_nodes as $potential_receiving_node) {
					$potential_receiving_node->setAttr('_next_annotations', $next_annotations);
				}
			}
		}

		// Collect state relation
		$state_relation = [];
		foreach (array_merge($graph->getNodesByAttr('type', 'startEvent'), $graph->getNodesByAttr('_potential_receiving')) as $node) {
			$next_annotations = $node->getAttr('_next_annotations');
			$next_invoking_nodes = $node->getAttr('_next_invoking_nodes');
			if (count($next_annotations) == 1) {
				// Use state specified by an annotation.
				$state_name = reset($next_annotations);
			} else if ($node->getAttr('type') == 'startEvent' && !$node->getAttr('_potential_receiving')) {
				// Implicit labeling because of the start event.
				$state_name = '';
			} else {
				// Check if an end event is reachable from this node
				$is_end_event_reachable = false;
				foreach ($next_invoking_nodes as $next_node) {
					if ($next_node->getAttr('type') == 'endEvent') {
						$is_end_event_reachable = true;
						break;
					}
				}
				if ($is_end_event_reachable) {
					// Implicit labeling because of the end event.
					$state_name = '';
				} else {
					// No state label, generate something "random"
					$state_name = 'Q_' . $node->getId();
				}
			}
			$state_relation[$node->getId()] = [$node, $next_invoking_nodes, $state_name];
		}

		// Collect transition relation
		$transition_relation = [];
		foreach ($graph->getNodesByAttr('_invoking') as $node) {
			$t = $transition_relation[$node->getId()] = [$node, $node->getAttr('_receiving_nodes'), $node->getAttr('_action_name')];
		}

		// Collect states from state relation (_next_invoking_nodes)
		$states = [];
		foreach ($state_relation as list($s_source, $t_targets, $state_name)) {
			$states[$state_name] = [];
		}

		// Collect actions by combining state relation with transition relation
		$actions = [];
		foreach ($state_relation as list($s_source, $s_targets, $s_state_name)) {
			foreach ($s_targets as $s_target) {
				/** @var Node $s_target */
				$s_target_id = $s_target->getId();
				if (isset($transition_relation[$s_target_id])) {
					list($t_source, $t_targets, $t_action_name) = $transition_relation[$s_target_id];
					foreach ($t_targets as $t_target) {
						/** @var Node $t_target */
						list($ts_source, $ts_target, $ts_state_name) = $state_relation[$t_target->getId()];

						// Define the transition. The same transition may be created multiple times.
						$actions[$t_action_name]['transitions'][$s_state_name]['targets'][$ts_state_name] = $ts_state_name;
					}
				}
			}
		}

		// Collect the results into the state machine definition
		$machine_def = [
			'states' => $states,
			'actions' => empty($errors) ? $actions : [],  // no actions if there are errors
		];

		// Sort the result, so all diagrams, menus, and other stuff does not get rearranged with every little change
		$this->ksortRecursive($machine_def);

		return [ $machine_def, $errors ];
	}


	/**
	 * Recursive implementation of ksort
	 *
	 * The $array is sorted in-place.
	 */
	private function ksortRecursive(array & $array): array
	{
		ksort($array);
		foreach ($array as & $item) {
			if (is_array($item)) {
				$this->ksortRecursive($item);
			}
		}
		return $array;
	}


	protected function renderBpmn($prefix, $fragment_file, $fragment, $errors)
	{
		/** @var Graph $graph */
		$graph = $fragment['graph'];

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
		$hidden_nodes = [];
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


	protected function renderBpmnJson(array & $machine_def, $prefix, $fragment_file, $fragment, $errors)
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

		// Draw provided SVG file as a node (for SVG export from Camunda Modeler)
		if (isset($fragment['svg_file_contents'])) {
			$svg_style = '';

			// Style nodes
			foreach ($graph->getAllNodes() as $id => $node) {
				$nodeAttrs = $this->renderBpmnProcessNodeAttrs($node, [], $prefix);

				// Don't style annotations
				if (isset($nodeAttrs['shape']) && $nodeAttrs['shape'] == 'note') {
					continue;
				}
				// Top-level shape
				$svg_style .= ".djs-element[data-element-id=$id] .djs-visual > *:first-child {";
				if (isset($nodeAttrs['fill'])) {
					$svg_style .= " fill:" . $nodeAttrs['fill'] . " !important;";
				}
				$svg_style .= " }\n";
				// All shapes
				$svg_style .= ".djs-element[data-element-id=$id] .djs-visual > * {";
				if (isset($nodeAttrs['color'])) {
					$svg_style .= " stroke:" . $nodeAttrs['color'] . " !important;";
				}
				$svg_style .= " }\n";
			}

			// Style arrows
			foreach ($graph->getAllEdges() as $id => $edge) {
				$edgeAttrs = $this->renderBpmnProcessEdgeAttrs($edge, []);

				$svg_style .= ".djs-element[data-element-id=$id] .djs-visual * {";
				if (isset($edgeAttrs['color'])) {
					$svg_style .= " stroke:" . $edgeAttrs['color'] . " !important;";
				}
				$svg_style .= " }\n";
			}

			// Close styles
			$svg_style .= ".djs-element .djs-visual text, .djs-element .djs-visual text * { stroke: none !important; }\n";

			// Build extras wrapper node
			$svg_diagram_node = [
				'id' => $prefix . '__svg',
				'label' => "BPMN: " . basename($fragment_file) . ' [' . basename($fragment['svg_file_name']) . ']',
				'color' => "#5373B4",
				'graph' => [
					'layout' => 'column',
					'layoutOptions' => [
						'sortNodes' => false,
					],
					'nodes' => [
					],
					'edges' => [],
				],
			];

			// Render errors (somehow)
			foreach ($errors as $err) {
				$err_node_id = $prefix . '__svg_error_' . md5($err['text']);
				$svg_diagram_node['graph']['nodes'][] = [
					'id' => $err_node_id,
					'color' => "#f00",
					'fill' => "#fee",
					'label' => 'Error: ' . $err['text'],
				];
				foreach ($err['nodes'] as $n) {
					/** @var Node $n */
					$n_id = $n->getId();
					$svg_style .= ".djs-element[data-element-id=$n_id] > .djs-outline {"
						. " fill: rgba(255, 0, 0, 0.05) !important;"
						. " stroke: #f00 !important;"
						. " stroke-width: 2 !important;"
						. "}\n";
				}
			}

			// Gradient definitions
			$svg_def_el = '<defs>'
				. '<linearGradient id="' . $prefix . '_gradient_rcv_inv">'
				. '<stop offset="50%" stop-color="#ff8" />'
				. '<stop offset="50%" stop-color="#adf" />'
				. '</linearGradient>'
				. '<linearGradient id="' . $prefix . '_gradient_pos_rcv">'
				. '<stop offset="50%" stop-color="#fff" />'
				. '<stop offset="50%" stop-color="#adf" />'
				. '</linearGradient>'
				. '</defs>';

			// Build style element
			$svg_style_el = "<style type=\"text/css\">" . htmlspecialchars($svg_style) . "</style>";
			$svg_file_contents = $fragment['svg_file_contents'];
			$svg_end_pos = strrpos($svg_file_contents, '</svg>');
			$svg_contents_with_style = substr_replace($svg_file_contents, $svg_style_el . $svg_def_el, $svg_end_pos, 0);

			// Warn if SVG file is obsolete
			if ($fragment['svg_file_is_obsolete']) {
				$svg_diagram_node['graph']['nodes'][] = [
					'id' => $prefix . '__svg_img_obsolete_warning',
					'shape' => 'label',
					'label' => 'Warning: Exported SVG file is older than source BPMN file.',
					'color' => '#aa2200',
				];
			}

			// Create image node
			$svg_diagram_node['graph']['nodes'][] = [
				'id' => $prefix . '__svg_img',
				'shape' => 'svg',
				'svg' => $svg_contents_with_style,
			];

			$machine_def['state_diagram_extras_json']['nodes'][] = $svg_diagram_node;
		}

		// Append extras to machine definition
		if (!isset($fragment['svg_file_contents'])) {
			$grafovatkoExport = (new GraphExportGrafovatko($graph))
				->setPrefix($prefix)
				->setGraphProcessor(function (NestedGraph $nestedGraph, array $exportedGraph) use ($graph, $prefix) {
					if ($nestedGraph === $graph) {
						$exportedGraph['layout'] = 'column';
						$exportedGraph['layoutOptions'] = [
							'sortNodes' => true,
						];
					} else {
						if ($nestedGraph->getParentNode()->getAttr('_is_state_machine', false)) {
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
				})
				->setNodeAttrsProcessor(function (Node $node, array $exportedNode) use ($prefix) {
					return $this->renderBpmnProcessNodeAttrs($node, $exportedNode, $prefix);
				})
				->setEdgeAttrsProcessor(function (Edge $edge, array $exportedEdge) use ($prefix) {
					return $this->renderBpmnProcessEdgeAttrs($edge, $exportedEdge);
				});

			$machine_def['state_diagram_extras_json']['nodes'][] = [
				'id' => $prefix . '_extra_graph',
				'label' => "BPMN: " . basename($fragment_file),
				'color' => "#5373B4",
				'graph' => $grafovatkoExport->export(),
			];
			$machine_def['state_diagram_extras_json']['extraSvg'][] = ['defs', [], [
				['linearGradient', ['id' => $prefix . '_gradient_rcv_inv'], [
					['stop', ['offset' => '50%', 'stop-color' => '#ff8']],
					['stop', ['offset' => '50%', 'stop-color' => '#adf']],
				]],
				['linearGradient', ['id' => $prefix . '_gradient_pos_rcv'], [
					['stop', ['offset' => '50%', 'stop-color' => '#fff']],
					['stop', ['offset' => '50%', 'stop-color' => '#adf']],
				]],
			]];
		}
	}


	private function renderBpmnProcessNodeAttrs(Node $node, array $exportedNode, string $prefix)
	{
		$exportedNode['fill'] = "#fff";

		// Node label
		$label = trim($node['name']);
		if ($node['_generated'] && $label != '') {
			$label = "($label)";
		}
		$exportedNode['label'] = $label;

		if ($node['type'] != 'participant') {
			$exportedNode['tooltip'] = json_encode($node->getAttributes(), JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		}

		// Node type (symbol)
		switch ($node['type']) {
			case 'task':
			case 'sendTask':
			case 'receiveTask':
			case 'userTask':
			case 'serviceTask':
				$exportedNode['shape'] = 'bpmn.task';
				break;

			case 'participant':
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
		} else {
			if ($node['_state']) {
				$exportedNode['color'] = '#66aa22';
			} else {
				if ($node['type'] == 'textAnnotation') {
					$exportedNode['color'] = '#aaaaaa';
				} else {
					$exportedNode['color'] = '#000000';
				}
			}
		}

		// Receiving/invoking background
		if ($node['_invoking'] && $node['_receiving']) {
			$exportedNode['fill'] = 'url(#' . $prefix . '_gradient_rcv_inv)';
		} else {
			if ($node['_invoking']) {
				$exportedNode['fill'] = '#ffff88';
			} else {
				if ($node['_receiving']) {
					$exportedNode['fill'] = '#aaddff';
				} else {
					if ($node['_potential_receiving']) {
						$exportedNode['fill'] = '#eeeeff';
						$exportedNode['fill'] = 'url(#' . $prefix . '_gradient_pos_rcv)';
					}
				}
			}
		}
		return $exportedNode;
	}


	private function renderBpmnProcessEdgeAttrs(Edge $edge, array $exportedEdge)
	{
		$label = trim($edge['name']);
		if ($edge['_generated'] && $label != '') {
			$label = "($label)";
		}

		$exportedEdge['label'] = $label;
		$exportedEdge['tooltip'] = json_encode($edge->getAttributes(), JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

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

}

