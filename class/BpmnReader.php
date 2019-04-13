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
use Smalldb\StateMachine\Graph\Grafovatko\ClosureProcessor;
use Smalldb\StateMachine\Graph\Graph;
use Smalldb\StateMachine\Graph\Grafovatko\GrafovatkoExporter;
use Smalldb\StateMachine\Graph\GraphSearch;
use Smalldb\StateMachine\Graph\MissingElementException;
use Smalldb\StateMachine\Graph\NestedGraph;
use Smalldb\StateMachine\Graph\Node;


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
 * @see https://camunda.org/bpmn/tool/
 */
class BpmnReader
{
	public $disableSvgFile = false;
	public $rewriteGraph = false;

	private $bpmnGraph;


	public function __construct()
	{
		$this->bpmnGraph = new Graph();

		$this->bpmnGraph->indexNodeAttr('type');
		$this->bpmnGraph->indexEdgeAttr('type');
	}


	public function getBpmnGraph(): Graph
	{
		return $this->bpmnGraph;
	}


	public function loadBpmnFile(string $bpmnFileName, string $state_machine_participant_id, ?string $svgFileName = null)
	{
		// Load GraphML into DOM
		$dom = new \DOMDocument;
		$dom->load($bpmnFileName);

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
		$rootNode = $this->bpmnGraph->createNode('bpmn_' . md5($bpmnFileName), [
			'type' => 'bpmnDiagram',
			'label' => basename($bpmnFileName),
		]);
		$graph = $rootNode->getNestedGraph();

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

				$source = $graph->getRootGraph()->getNodeById($sourceRef);
				$target = $graph->getRootGraph()->getNodeById($targetRef);

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
				$source = $graph->getRootGraph()->getNodeById(trim($el->getAttribute('sourceRef')));
				$target = $graph->getRootGraph()->getNodeById(trim($el->getAttribute('targetRef')));
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

		return $this->inferStateMachine($this->bpmnGraph, $state_machine_participant_id, $state_machine_process_id);

		/*
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
		*/
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
		$this->bpmnGraph->indexNodeAttr('_invoking');
		$this->bpmnGraph->indexNodeAttr('_receiving');
		$this->bpmnGraph->indexNodeAttr('_potential_receiving');

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


}

