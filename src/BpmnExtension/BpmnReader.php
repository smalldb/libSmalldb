<?php declare(strict_types = 1);
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

namespace Smalldb\StateMachine\BpmnExtension;

use DOMDocument;
use DomElement;
use DOMXPath;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilder;
use Smalldb\Graph\Edge;
use Smalldb\Graph\Graph;
use Smalldb\Graph\GraphSearch;
use Smalldb\Graph\MissingElementException;
use Smalldb\Graph\Node;
use Smalldb\StateMachine\Definition\DefinitionError;


/**
 * BPMN reader
 *
 * Read a BPMN diagram and infer a state machine which implements a given
 * participant of the business proces. When multiple BPMN loaders used,
 * the final state machine will implement all of the business processes.
 *
 * @see https://camunda.org/bpmn/tool/
 */
class BpmnReader
{
	private Graph $bpmnGraph;
	private ?string $bpmnFileName = null;
	private ?string $svgFileName = null;
	private bool $timeLogEnabled = false;
	private float $timeStart;
	/** @var float[] */
	private array $timeLog = [];


	private function __construct()
	{
	}


	private function logTimeStart(string $keyStart): void
	{
		if ($this->timeLogEnabled) {
			$u = getrusage();
			$t = ($u['ru_utime.tv_sec'] + $u['ru_utime.tv_usec'] / 1e6);
			$this->timeStart = $t;
			$this->timeLog = [$keyStart => 0];
		}
	}


	private function logTime(string $key): void
	{
		if ($this->timeLogEnabled) {
			$u = getrusage();
			$t = ($u['ru_utime.tv_sec'] + $u['ru_utime.tv_usec'] / 1e6);
			$this->timeLog[$key] = $t - $this->timeStart;
		}
	}


	public function enableTimeLog(bool $enable = true)
	{
		$this->timeLogEnabled = $enable;
	}


	public function getTimeLog(): array
	{
		return $this->timeLog;
	}


	public static function readBpmnFile(string $bpmnFileName): self
	{
		$reader = new self();
		$reader->bpmnGraph = $reader->parseBpmnFile($bpmnFileName);
		$reader->bpmnFileName = $bpmnFileName;
		return $reader;
	}


	public static function readGraph(Graph $bpmnGraph): self
	{
		$reader = new self();
		$reader->bpmnGraph = $bpmnGraph;
		return $reader;
	}


	public function getBpmnGraph(): Graph
	{
		return $this->bpmnGraph;
	}


	public function setSvgFileName(?string $svgFileName): void
	{
		$this->svgFileName = $svgFileName;
	}


	public function getSvgFileName(): ?string
	{
		return $this->svgFileName;
	}


	private function parseBpmnFile(string $bpmnFileName): Graph
	{
		// Load GraphML into DOM
		$dom = new DOMDocument;
		$dom->load($bpmnFileName);

		// Prepare XPath query engine
		$xpath = new DOMXPath($dom);
		$xpath->registerNameSpace('bpmn', 'http://www.omg.org/spec/BPMN/20100524/MODEL');

		// Create the Graph
		$bpmnGraph = new Graph();

		// Lets collect arrows, events and tasks (still in BPMN semantics)
		$processes = [];
		$participants = [];

		/** @var Graph[] $processNestedGraphs */
		$processNestedGraphs = [];

		// Get participants and their processes
		foreach ($xpath->query('//bpmn:participant[@id]') as $el) {
			/** @var DomElement $el */
			$id = trim($el->getAttribute('id'));
			$name = trim($el->getAttribute('name'));
			$process_id = $el->getAttribute('processRef');

			if ($process_id === "") {
				$process_id = "#__$id-process";
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

			$node = $bpmnGraph->createNode($id, [
				'id' => $id,
				'name' => $name,
				'type' => 'participant',
				'process' => $process_id,
				'features' => [],
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
			'textAnnotation'] as $type
		) {
			foreach ($xpath->query('//bpmn:' . $type . '[@id]') as $el) {
				/** @var DomElement $el */
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
					throw new BpmnException("Process graph \"$process_id\" not found for node \"$id\".");
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
			foreach ($xpath->query('//bpmn:' . $type . '[@id][@sourceRef][@targetRef]') as $el) {
				/** @var DomElement $el */

				// Arrow properties
				$id = trim($el->getAttribute('id'));
				$name = trim($el->getAttribute('name'));
				$sourceRef = trim($el->getAttribute('sourceRef'));
				$targetRef = trim($el->getAttribute('targetRef'));

				$source = $bpmnGraph->getNodeById($sourceRef);
				$target = $bpmnGraph->getNodeById($targetRef);

				$sourceGraph = $source->getGraph();
				$targetGraph = $target->getGraph();

				$edgeGraph = ($sourceGraph === $targetGraph ? $sourceGraph : $bpmnGraph);

				// Store arrow
				$edgeGraph->createEdge($id, $source, $target, [
					'id' => $id,
					'type' => $type,
					'name' => $name,
				]);
			}
		}

		// Get annotations' associations
		foreach ($xpath->query('//bpmn:association[@id]') as $el) {
			/** @var DomElement $el */
			try {
				$source = $bpmnGraph->getNodeById(trim($el->getAttribute('sourceRef')));
				$target = $bpmnGraph->getNodeById(trim($el->getAttribute('targetRef')));
			}
			catch (MissingElementException $ex) {
				continue;
			}

			$sourceType = $source->getAttr('type');
			$targetType = $target->getAttr('type');

			$edgeGraph = ($source->getGraph() === $target->getGraph() ? $source->getGraph() : $bpmnGraph);

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

		return $bpmnGraph;
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

		if ($errorGraph) {
			$errorNodeId = '_error_' . md5($message) . '_' . count($errors);
			$errorNode = $errorGraph->createNode($errorNodeId, ['label' => $message, 'type' => 'error']);
			foreach ($nodes as $node) {
				$errorGraph->createEdge(null, $errorNode, $node, ['type' => 'error']);
			}
		}
	}

	private function findParticipantNode(string $state_machine_participant_id): Node
	{
		if (!preg_match('/^[a-zA-Z0-9_.-]*$/', $state_machine_participant_id)) {
			throw new BpmnException('Invalid participant ID provided '
				.'(only alphanumeric characters, underscore, dot and dash are allowed): '
				. var_export($state_machine_participant_id, true));
		}

		try {
			return $this->bpmnGraph->getNodeById($state_machine_participant_id);
		}
		catch (MissingElementException $ex) {
			throw new BpmnException('Participant representing the state machine not found: ' . $state_machine_participant_id);
		}
	}


	public function inferStateMachine(StateMachineDefinitionBuilder $builder,
		string $state_machine_participant_id, bool $rewriteGraph = false): StateMachineDefinitionBuilder
	{
		$this->logTimeStart('start');

		$errors = [];

		// Index node and edge type
		$this->bpmnGraph->indexNodeAttr('type');
		$this->bpmnGraph->indexEdgeAttr('type');

		// Add few more indices -- define I, R, R+ sets
		$this->bpmnGraph->indexNodeAttr('_invoking'); // I set
		$this->bpmnGraph->indexNodeAttr('_receiving'); // R set
		$this->bpmnGraph->indexNodeAttr('_potential_receiving'); // R+ set

		// Get participant
		$stateMachineNode = $this->findParticipantNode($state_machine_participant_id);
		$state_machine_process_id = $stateMachineNode->getAttr('process');

		$this->logTime('init');

		// Stage 1: Add implicit tasks to BPMN diagram -- invoking message flow targets
		if ($rewriteGraph) {
			foreach ($this->bpmnGraph->getAllEdges() as $edge) {
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
			$this->logTime('rewrite');
		}

		// Stage 1: Find message flows to state machine participant, identify
		// invoking and potential receiving nodes
		foreach ($this->bpmnGraph->getAllEdges() as $edgeId => $a) {
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
				} else if ($target['process'] !== $state_machine_process_id) {
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
		$this->logTime('1-I-R+');

		// Stage 1: Find receiving nodes for each invoking node
		// (DFS to next task or event, the receiver cannot be further than that)
		foreach ($this->bpmnGraph->getNodesByAttr('_invoking') as $in_id => $invoking_node) {
			$invoking_node->setAttr('_receiving_nodes', []);
			$invoking_process = $invoking_node['process'];
			/** @var Node[] $receiving_nodes */
			$receiving_nodes = [];
			/** @var Edge[] $visited_arrows */
			$visited_arrows = [];
			/** @var Node[] $visited_nodes */
			$visited_nodes = [];

			GraphSearch::DFS($this->bpmnGraph)
				->onEdge(function(Node $cur_node, Edge $edge, Node $next_node, bool $seen)
					use ($invoking_process, & $receiving_nodes, & $visited_arrows, & $visited_nodes)
				{
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
				if ($rewriteGraph && $invoking_arrow) {
					// Add receiving arrow only if there is invoking arrow
					// (timer events may represent transitions without invoking arrow).
					$new_id = 'x_'.$in_id.'_receiving';
					$this->bpmnGraph->createEdge($new_id, $invoking_arrow->getEnd(), $invoking_arrow->getStart(), [
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

					if ($rewriteGraph && $rcv_arrow && $rcv_arrow->getStart()->getId() == $state_machine_participant_id) {
						if ($invoking_arrow === null) {
							throw new BpmnException("Missing invoking arrow. This should not happen.");  // @codeCoverageIgnore
						}
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
		$this->logTime('1-R');

		// Stage 1: Remove receiving tag from nodes without action
		/** @var Node[] $active_receiving_nodes */
		$active_receiving_nodes = [];
		foreach ($this->bpmnGraph->getNodesByAttr('_invoking', true) as $id => $node) {
			foreach ($node['_receiving_nodes'] as $rcv_node) {
				/** @var Node $rcv_node */
				$active_receiving_nodes[$rcv_node->getId()] = $rcv_node;
			}
		}
		foreach ($this->bpmnGraph->getNodesByAttr('_receiving', true) as $id => $node) {
			if (empty($active_receiving_nodes[$id])) {
				$node->setAttr('_receiving', false);
			}
		}
		$this->logTime('1-rm');

		// Stage 3: Detect state machine annotation symbol
		$state_machine_participant_node = $this->bpmnGraph->getNodeById($state_machine_participant_id);
		if (preg_match('/^\s*(@[^:\s]+)(|:\s*.+)$/', $state_machine_participant_node['name'], $m)) {
			$state_machine_annotation_symbol = $m[1];
		} else {
			$state_machine_annotation_symbol = '@';
		}
		$this->logTime('3-ann');

		// Stage 3: Collect name states from annotations
		$custom_state_names = [];
		foreach ($this->bpmnGraph->getAllNodes() as $n_id => $node) {
			if ($node['type'] == 'participant' || $node['type'] == 'error' || $node['type'] == 'annotation' || $node['process'] == $state_machine_process_id) {
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
			sort($ann_state_names);
			$ann_state_names = array_unique($ann_state_names);
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
		$this->logTime('3-ann-parse');

		// Stage 2: State relation
		foreach (array_merge($this->bpmnGraph->getNodesByAttr('type', 'startEvent'), $this->bpmnGraph->getNodesByAttr('_potential_receiving'))  as $receiving_node_id => $receiving_node) {
			/** @var Node $receiving_node */
			/** @var Node[] $next_invoking_nodes */
			$next_invoking_nodes = [];
			$next_annotations = [];

			GraphSearch::DFS($this->bpmnGraph)
				->onEdge(function(Node $cur_node, Edge $edge, Node $next_node, bool $seen)
					use ($state_machine_process_id, $receiving_node_id, & $next_invoking_nodes)
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

			GraphSearch::DFS($this->bpmnGraph)
				->runBackward()
				->onEdge(function(Node $cur_node, Edge $edge, Node $next_node, bool $seen)
					use ($receiving_node_id, & $next_annotations)
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
		$this->logTime('2-S');

		// Implicit state propagation
		foreach ($this->bpmnGraph->getNodesByAttr('_receiving') as $id => $receiving_node) {
			// Get invoking node as the returning message flow may be implicit (and not drawn)
			/** @var Node $invoking_node */
			$invoking_node = $receiving_node['_invoking_node'];

			// Find task node (there should be a single incoming message flow)
			$task_node = null;
			foreach ($invoking_node->getConnectedEdges() as $edge) {
				$edgeStart = $edge->getStart();
				$edgeEnd = $edge->getEnd();
				if ($edge->getAttr('type') == 'messageFlow' && $edgeStart === $invoking_node
					&& $edgeEnd->getId() !== $state_machine_participant_id
					&& $edgeEnd->getAttr('process') === $state_machine_process_id)
				{
					if ($task_node === null) {
						// Found the first incoming message flow
						$task_node = $edgeEnd;
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
		$this->logTime('2-impl-S');

		// Collect state relation
		$state_relation = [];
		foreach (array_merge($this->bpmnGraph->getNodesByAttr('type', 'startEvent'), $this->bpmnGraph->getNodesByAttr('_potential_receiving')) as $node) {
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
		$this->logTime('2-S-rel');

		// Collect transition relation
		$transition_relation = [];
		foreach ($this->bpmnGraph->getNodesByAttr('_invoking') as $node) {
			$transition_relation[$node->getId()] = [$node, $node->getAttr('_receiving_nodes'), $node->getAttr('_action_name')];
		}
		$this->logTime('2-T-rel');

		// Collect states from state relation (_next_invoking_nodes)
		$states = [];
		foreach ($state_relation as list($s_source, $t_targets, $state_name)) {
			$states[$state_name] = $state_name;
		}
		$this->logTime('4-S');

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
						$actions[$t_action_name][$s_state_name][$ts_state_name] = $ts_state_name;
					}
				}
			}
		}
		$this->logTime('4-T');

		// We have everything ready, time to build the state machine definition.
		foreach ($states as $state) {
			if ($state !== '') {
				$builder->addState($state);
			}
		}
		foreach ($actions as $action_name => $action_transitions) {
			$builder->addAction((string)$action_name);
			foreach ($action_transitions as $source_state => $target_states) {
				$builder->addTransition((string)$action_name, (string)$source_state, $target_states);
			}
		}
		$this->logTime('4-def');

		// Add errors to $builder so we won't use broken state machines
		foreach ($errors as $error) {
			$builder->addError(new DefinitionError($error['text']));
		}

		$builder->sortPlaceholders();
		$this->logTime('done');
		return $builder;
	}


}

