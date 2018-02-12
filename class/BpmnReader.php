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

		// Get processes (groups)
		foreach($xpath->query('//bpmn:process[@id]') as $el) {
			/** @var \DomElement $el */
			$id = trim($el->getAttribute('id'));
			$name = trim($el->getAttribute('name'));

			$processes[$id] = [
				'id' => $id,
				'name' => $name,
				'participant' => null,
				'nodes' => [],
				'_generated' => false,
			];
		}

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
				'_invoking' => false,
				'_receiving' => false,
				'_possibly_receiving' => false,
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
					'_invoking' => false,
					'_receiving' => false,
					'_possibly_receiving' => false,
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
		} else {
			$svg_file_name = null;
			$svg_file_contents = null;
		}

		// Store fragment in state machine definition
		return [
			'bpmn_fragments' => [
				$filename.'#'.$state_machine_participant_id => [
					'file' => $filename,
					'state_machine_participant_id' => $state_machine_participant_id,
					'state_machine_process_id' => $state_machine_process_id,
					'graph' => $graph,
					'groups' => $processes,
					'participants' => $participants,
					'svg_file_name' => $svg_file_name,
					'svg_file_contents' => $svg_file_contents,
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
			list($fragment_machine_def, $fragment_errors) = $this->inferStateMachine($fragment, $errors);

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

		$errorNodeId = '_error_'.md5($message);
		$errorNode = $errorGraph->createNode($errorNodeId, ['label' => $message, 'type' => 'error']);
		foreach ($nodes as $node) {
			$errorGraph->createEdge(null, $errorNode, $node, ['type' => 'error']);
		}
	}


	protected function inferStateMachine(& $fragment, & $errors)
	{
		// Results
		$machine_def = [];

		// Get BPMN graph and setup additional inidices
		/** @var Graph $graph */
		$graph = $fragment['graph'];
		$graph->indexNodeAttr('_invoking');
		$graph->indexNodeAttr('_receiving');
		$graph->indexNodeAttr('_possibly_receiving');

		// Shortcuts
		$state_machine_process_id = & $fragment['state_machine_process_id'];
		$state_machine_participant_id = & $fragment['state_machine_participant_id'];
		$state_machine_participant_node = $graph->getNodeById($state_machine_participant_id);


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
				} else {
					$source['_action_name'] = $a->getId();
				}
			}

			// Receiving message flow
			if ($target['process'] != $state_machine_process_id && ($source['process'] == $state_machine_process_id)) {
				$target->setAttr('_receiving', true);
				$target->setAttr('_possibly_receiving', true);
				if ($target['_action_name'] !== null && $target['_action_name'] != $source->getId()) {
					$this->addError($errors, 'Multiple actions invoked by a single task.', [$target]);
				} else {
					$target['_action_name'] = $a->getId();
				}
			}
		}

		// Stage 1: Add implicit tasks to BPMN diagram -- invoking message flow targets
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
					'process' => $fragment['state_machine_process_id'],
					'features' => [],
					'_generated' => true,
				]);
				$groups[$state_machine_process_id]['nodes'][] = $new_node_id;
				$edge->setEnd($new_node);
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
				if ($invoking_arrow) {
					// Add receiving arrow only if there is invoking arrow
					// (timer events may represent transitions without invoking arrow).
					$new_id = 'x_'.$in_id.'_receiving';
					$graph->createEdge($new_id, $invoking_arrow->getEnd(), $invoking_arrow->getStart(), [
						'id' => $new_id,
						'type' => 'messageFlow',
						'name' => $invoking_arrow['name'],
						'_transition' => false,
						'_state' => false,
						'_generated' => true,
					]);
				}
				$invoking_node->setAttr('_receiving', true);
				$invoking_node->setAttr('_possibly_receiving', true);
				$invoking_node['_receiving_nodes'][$invoking_node->getId()] = $invoking_node;
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

					if ($rcv_arrow && $rcv_arrow->getStart()->getId() == $state_machine_participant_id) {
						$rcv_arrow->setStart($invoking_arrow->getEnd());
					}

					$invoking_node['_receiving_nodes'][$rcv_node->getId()] = $rcv_node;
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
		foreach (array_merge($graph->getNodesByAttr('type', 'startEvent'), $graph->getNodesByAttr('_possibly_receiving'))  as $receiving_node_id => $receiving_node) {
			/** @var Node $receiving_node */
			/** @var Node[] $next_invoking_nodes */
			$next_invoking_nodes = [];
			$annotations = [];

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

					if ($next_node['_invoking'] || $next_node['type'] == 'endEvent') {
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
					use ($graph, $receiving_node_id, $receiving_node, & $next_invoking_nodes, & $annotations)
				{
					if (!isset($edge['_state_from'][$receiving_node_id]) || $edge['_state_from'][$receiving_node_id] !== false) {
						// Visit only what we visited on the forward run
						return false;
					}

					$edge['_state_from'][$receiving_node_id] = true;
					$cur_node['_state_from'][$receiving_node_id] = true;

					// Collect annotations on all paths from R+ to I
					if (isset($cur_node['_annotation_state']) && !$cur_node['_possibly_receiving'] && !$cur_node['_invoking']) {
						$annotations[$cur_node['_annotation_state']] = true;
					}
					if (isset($next_node['_annotation_state'])) {
						$annotations[$next_node['_annotation_state']] = true;
					}

					return true;
				})
				->start($next_invoking_nodes);

			$receiving_node->setAttr('_next_annotations', array_keys($annotations));
		}

		// Stage 1: (M_S) Find elements which are part of a state [deprecated]
		foreach ($graph->getAllNodes() as $node) {
			if ($node['type'] != 'textAnnotation' && $node['type'] != 'participant'
				&& $node['process'] != $state_machine_process_id
				&& !$node['_transition'] && !$node['_invoking'] && !$node['_receiving'])
			{
				if (isset($node['_state_from'])) {
					foreach ($node['_state_from'] as $receiving_node_id => $is_on_path) {
						if ($is_on_path) {
							$node->setAttr('_state', true);
							break;
						}
					}
				}
			}
		}
		foreach ($graph->getAllEdges() as $edge) {
			$source = $edge->getStart();
			$target = $edge->getEnd();
			if ($edge['type'] == 'sequenceFlow' && $source['process'] != $state_machine_process_id && !$source['_transition']
				&& $target['process'] != $state_machine_process_id && !$target['_transition'])
			{
				if (isset($edge['_state_from'])) {
					foreach ($edge['_state_from'] as $receiving_node_id => $is_on_path) {
						if ($is_on_path) {
							$edge->setAttr('_state', true);
							break;
						}
					}
				}
			}
		}

		// Stage 2: (s) State detection -- Merge green arrows and nodes into states
		$uf = new UnionFind();
		foreach ($graph->getAllNodes() as $id => $node) {
			if ($node['_invoking']) {
				$uf->add('Qin_'.$id);
			}
			if ($node['_receiving']) {
				$uf->add('Qout_'.$id);
			}
		}
		foreach ($graph->getAllEdges() as $id => $edge) {
			if ($edge['_state']) {
				$sourceId = $edge->getStart()->getId();
				$targetId = $edge->getEnd()->getId();

				// Add entry and exit points
				$uf->add('Qout_'.$sourceId);
				$uf->add('Qin_'.$targetId);
				$uf->union('Qout_'.$sourceId, 'Qin_'.$targetId);

				// Add the arrow itself, so we can find to which state it belongs
				$uf->addUnique($id);
				$uf->union($id, 'Qin_'.$targetId);
			}
		}
		foreach ($graph->getAllNodes() as $id => $node) {
			if ($node['_state']) {
				// Connect input with output as this node is pass-through, unless it is a receiving node
				$uf->add('Qout_'.$id);
				if (!$node['_possibly_receiving']) {
					$uf->add('Qin_'.$id);
					$uf->union('Qin_'.$id, 'Qout_'.$id);
				}

				// Add the node itself, so we can find to which state it belongs
				$uf->addUnique($id);
				$uf->union($id, 'Qout_'.$id);
			}
		}

		// Stage 2: State propagation -- all message flows from a single task
		// in the state machine process end in the same state.
		foreach ($graph->getAllNodes() as $id => $node) {
			if ($node['process'] == $state_machine_process_id && $node['type'] == 'task') {
				// Collect nodes to which message flows flow
				/** @var Node[] $receiving_nodes */
				$receiving_nodes = [];
				/** @var Node[] $targets */
				$targets = [];
				/** @var Edge[] $state_arrows */
				$state_arrows = [];
				foreach ($node->getConnectedEdges() as $edgeId => $edge) {
					if ($edge->getStart() === $node) {
						if ($edge['type'] == 'messageFlow') {
							$target = $edge->getEnd();
							if ($target['_receiving']) {
								// There should be one receiving node ...
								$receiving_nodes[$target->getId()] = $target;
							} else {
								// ... and multiple other targets
								$targets[$target->getId()] = $target;
							}
							$state_arrows[$edge->getId()] = $edge;
						}
					}
				}

				if (!empty($targets) && count($receiving_nodes) == 1) {
					// If there are targets, define the state equivalence
					$rcv = reset($receiving_nodes);
					$rcvId = $rcv->getId();
					$uf->add('Qout_'.$rcvId);
					foreach ($targets as $t => $target) {
						$uf->add('Qout_'.$t);
						$uf->union('Qout_'.$rcvId, 'Qout_'.$t);

						// Assign state to the target node
						$target->setAttr('_state', true);
						$uf->add($t);
						$uf->union('Qout_'.$t, $t);
					}
					foreach ($state_arrows as $e => $edge) {
						// Assign state to the arrow
						$edge->setAttr('_state', true);
						$uf->add($e);
						$uf->union('Qout_'.$rcvId, $e);
					}
				}
			}
		}

		// Stage 3: Assign state names to states (UnionFind will use them as they are added last)
		foreach ($custom_state_names as $state => $node_ids) {
			$uf->addUnique($state);
			foreach ($node_ids as $node_id) {
				$node = $graph->getNodeById($node_id);
				if ($node['_state'] || $node['_receiving'] || $node['type'] == 'startEvent') {
					$uf->union($state, 'Qout_'.$node_id);
				} else if ($node['_invoking'] || $node['type'] == 'endEvent') {
					$uf->union($state, 'Qin_'.$node_id);
				} else {
					$this->addError($errors, 'Unused annotation.', [$node_id]);
				}
			}
		}

		// Stage 4: Mark unused states (no invoking nor receiving nodes)
		// These states are created from unreachable portions of the diagram.
		// We do this before the implicit labeling to mark unused branches
		// in BPMN diagram, but then we ignore such removals.
		$used_s = [];
		foreach ($graph->getNodesByAttr('_invoking', true) as $n_id => $node) {
			$n_in = 'Qin_'.$n_id;
			if ($uf->has($n_in)) {
				$s = $uf->find($n_in);
				$used_s[$s] = true;
			}
		}
		foreach ($graph->getNodesByAttr('_receiving', true) as $n_id => $node) {
			$n_out = 'Qout_'.$n_id;
			if ($uf->has($n_out)) {
				$s = $uf->find($n_out);
				$used_s[$s] = true;
			}
		}
		foreach ($graph->getAllNodes() as $n_id => $node) {
			if ($node['_state'] && $uf->has($n_id)) {
				$s = $uf->find($n_id);
				if (empty($used_s[$s])) {
					$node->setAttr('_unused', true);
				}
			}
		}
		foreach ($graph->getAllEdges() as $edgeId => $edge) {
			// No need to check the target node, because the source would have to be a receiving node.
			$n_id = $edge->getStart()->getId();
			if ($edge['_state'] && $uf->has($n_id)) {
				$s = $uf->find($n_id);
				if (empty($used_s[$s])) {
					$edge->setAttr('_unused', true);
				}
			}
		}

		// Stage 3: Add implicit '' for start states
		foreach ($graph->getNodesByAttr('type', 'startEvent') as $s_id => $s_n) {
			$s = 'Qout_'.$s_id;
			$uf->add($s);
			if (!isset($custom_state_names[$uf->find($s)])) {
				$uf->add('');
				$uf->union('', $s);
			}
		}

		// Stage 3: Add implicit '' for final states
		foreach ($graph->getNodesByAttr('type', 'endEvent') as $e_id => $e_n) {
			$s = 'Qin_'.$e_id;
			$uf->add($s);
			if (!isset($custom_state_names[$uf->find($s)])) {
				$uf->add('');
				$uf->union('', $s);
			}
		}

		// Stage 3: Check that two custom states are not merged into one
		foreach ($custom_state_names as $a => $na) {
			foreach ($custom_state_names as $b => $nb) {
				if ($a !== $b && $uf->find($a) === $uf->find($b)) {
					$n = array_merge($na, $nb);
					sort($n);
					$this->addError($errors, 'Annotations define multiple names for a single state (found when merging): '.join(', ', [$a, $b]), $n);
					break 2;
				}
			}
		}

		// Stage 4: Create states from s(Ih) and s(Rh)
		$states = [];
		foreach ($graph->getNodesByAttr('_invoking', true) as $n_id => $node) {
			$s = $uf->find('Qin_' . $n_id);
			$states[$s] = [];
		}
		foreach ($graph->getNodesByAttr('_receiving', true) as $n_id => $node) {
			$s = $uf->find('Qout_' . $n_id);
			$states[$s] = [];
		}

		// Stage 4: Find all transitions
		$actions = [];
		foreach ($graph->getNodesByAttr('_invoking', true) as $id => $node) {
			if (empty($node['_action_name'])) {
				// Skip invoking nodes without action
				continue;
			}
			// Get action
			$a_arrow = $graph->getEdgeById($node['_action_name']);
			$a_node = $a_arrow->getEnd();
			$action = $a_node['name'];

			// Define transition
			$state_before = $uf->find('Qin_'.$id);
			foreach($node['_receiving_nodes'] as $rcv_node) {
				$state_after = $uf->find('Qout_'.$rcv_node->getId());
				$actions[$action]['transitions'][$state_before]['targets'][] = $state_after;
			}
		}

		// Stage 4: [debug] At this point the state machine is complete, so let's assign states and transitions to BPMN nodes.
		foreach ($graph->getAllNodes() as $id => $node) {
			if ($node['_state']) {
				$node['_state_name'] = $uf->find($id);
			}
		}
		foreach ($graph->getAllEdges() as $id => $edge) {
			if ($edge['_state']) {
				$edge['_state_name'] = $uf->find($id);
			}
		}

		// [visualization] Calculate distance of each node from nearest start
		// event to detect backward arrows and sort nodes in state machine, so
		// the diagrams look much better
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
				if ($edge['type'] == 'sequenceFlow' && !$seen) {
					$next_node['_distance'] = $distance;
					return true;
				} else {
					return false;
				}
			})
			->start($graph->getNodesByAttr('type', 'startEvent'));

		// Store results in the state machine definition
		$machine_def['states'] = $states;
		$machine_def['actions'] = empty($errors) ? $actions : [];  // no actions if there are errors
		return [ $machine_def, $errors ];
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
			} else if ($n['_possibly_receiving']) {
				$diagram .= ",fillcolor=\"#eeeeee$alpha;0.5:#aaddff$alpha\",gradientangle=270";
			}

			// End of node.
			$diagram .= "];\n";

			// Draw annotation associations
			if (!empty($n['annotations'])) {
				foreach ($n['annotations'] as $ann_node_id => $ann_node) {
					$ann_graph_id = AbstractMachine::exportDotIdentifier($ann_node_id, $prefix);
					$diagram .= "\t\t" . $graph_id . " -> " . $ann_graph_id
						. " [id=\"" . addcslashes($prefix.$ann_node_id.'__line', "\"\n") . "\""
						.",style=dashed,color=\"#aaaaaa$alpha\",arrowhead=none];\n";
				}
			}
		}

		// Draw groups
		//*
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
		// */

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

		// Initialize node for the BPMN fragment
		$diagram_node = [
			'id' => $prefix,
			'label' => "BPMN: " . basename($fragment_file),
			'color' => "#5373B4",
			'graph' => [
				'layout' => 'column',
				'layoutOptions' => [
					'sortNodes' => true,
				],
				'nodes' => [],
				'edges' => [],
			],
		];

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
				$edgeAttrs = $this->renderBpmnProcessEdgeAttrs($edge, [], $prefix);

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
					return $this->renderBpmnProcessEdgeAttrs($edge, $exportedEdge, $prefix);
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
				$exportedNode['shape'] = 'rect';
				$exportedNode['label'] = $node['text'];
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
					if ($node['_possibly_receiving']) {
						$exportedNode['fill'] = '#eeeeff';
						$exportedNode['fill'] = 'url(#' . $prefix . '_gradient_pos_rcv)';
					}
				}
			}
		}
		return $exportedNode;
	}


	private function renderBpmnProcessEdgeAttrs(Edge $edge, array $exportedEdge, string $prefix)
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

