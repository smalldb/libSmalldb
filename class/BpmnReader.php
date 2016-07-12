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
 * 	- `process_id`: ID of BPMN process to use from the file (string). If
 * 	  null, the first process is used.
 *
 * @see https://camunda.org/bpmn/tool/
 */
class BpmnReader implements IMachineDefinitionReader
{

	/// @copydoc IMachineDefinitionReader::loadString
	public static function loadString($data_string, $options = array(), $filename = null)
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
			$process_element = $xpath->query('/bpmn:definitions/bpmn:process[@id=\''.$bpmn_process_id.'\']')->item(0);
		} else {
			$process_element = $xpath->query('/bpmn:definitions/bpmn:process')->item(0);
		}

		// Process element is mandatory
		if (!$process_element) {
			throw new BpmnException('Process element not found: '.var_export($bpmn_process_id, true));
		}

		// Lets collect arrows, events and tasks (still in BPMN semantics)
		$arrows = array();
		$nodes = array();

		// Get arrows -- the sequence flow
		foreach($xpath->query('./bpmn:sequenceFlow[@id][@sourceRef][@targetRef]', $process_element) as $el) {
			$id = $el->getAttribute('id');
			$name = $el->getAttribute('name');
			$sourceRef = $el->getAttribute('sourceRef');
			$targetRef = $el->getAttribute('targetRef');
			$arrows[$id] = array(
				'source' => $sourceRef,
				'target' => $targetRef,
				'name' => $name,
			);
		}

		// Get start events
		foreach($xpath->query('./bpmn:startEvent[@id]', $process_element) as $el) {
			$id = $el->getAttribute('id');
			$name = $el->getAttribute('name');

			$incoming = array();
			foreach($xpath->query('./bpmn:incoming/text()[1]', $el) as $in) {
				$incoming[] = $in->wholeText;
			}

			$outgoing = array();
			foreach($xpath->query('./bpmn:outgoing/text()[1]', $el) as $out) {
				$outgoing[] = $out->wholeText;
			}

			$nodes[$id] = array(
				'id' => $id,
				'name' => $name,
				'type' => 'start',
				'incoming' => $incoming,
				'outgoing' => $outgoing,
			);
		}

		// Get intermediate events
		foreach($xpath->query('./bpmn:intermediateThrowEvent[@id]', $process_element) as $el) {
			$id = $el->getAttribute('id');
			$name = $el->getAttribute('name');

			$incoming = array();
			foreach($xpath->query('./bpmn:incoming/text()[1]', $el) as $in) {
				$incoming[] = $in->wholeText;
			}

			$outgoing = array();
			foreach($xpath->query('./bpmn:outgoing/text()[1]', $el) as $out) {
				$outgoing[] = $out->wholeText;
			}

			$nodes[$id] = array(
				'id' => $id,
				'name' => $name,
				'type' => 'intermediate',
				'incoming' => $incoming,
				'outgoing' => $outgoing,
			);
		}

		// Get end events
		foreach($xpath->query('./bpmn:endEvent[@id]', $process_element) as $el) {
			$id = $el->getAttribute('id');
			$name = $el->getAttribute('name');

			$incoming = array();
			foreach($xpath->query('./bpmn:incoming/text()[1]', $el) as $in) {
				$incoming[] = $in->wholeText;
			}

			$outgoing = array();
			foreach($xpath->query('./bpmn:outgoing/text()[1]', $el) as $out) {
				$outgoing[] = $out->wholeText;
			}

			$nodes[$id] = array(
				'id' => $id,
				'name' => $name,
				'type' => 'end',
				'incoming' => $incoming,
				'outgoing' => $outgoing,
			);
		}

		// Get tasks
		foreach($xpath->query('./bpmn:task[@id]', $process_element) as $el) {
			$id = $el->getAttribute('id');
			$name = $el->getAttribute('name');

			$incoming = array();
			foreach($xpath->query('./bpmn:incoming/text()[1]', $el) as $in) {
				$incoming[] = $in->wholeText;
			}

			$outgoing = array();
			foreach($xpath->query('./bpmn:outgoing/text()[1]', $el) as $out) {
				$outgoing[] = $out->wholeText;
			}

			$nodes[$id] = array(
				'id' => $id,
				'name' => $name,
				'type' => 'task',
				'incoming' => $incoming,
				'outgoing' => $outgoing,
			);
		}

		// Dump BPMN fragment
		/*
		printf("\nBPMN diagram: %s, %s\n", basename($filename), $bpmn_process_id);
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
		printf("\n");
		// */

		return array(
			'bpmn_fragments' => array(
				$filename.'#'.$bpmn_process_id => array(
					'file' => $filename,
					'process_id' => $bpmn_process_id,
					'arrows' => $arrows,
					'nodes' => $nodes,
				),
			),
		);

	}


	/// @copydoc IMachineDefinitionReader::postprocessDefinition
	public static function postprocessDefinition(& $machine_def)
	{
		if (!isset($machine_def['bpmn_fragments'])) {
			return;
		}
		$bpmn_fragments = $machine_def['bpmn_fragments'];
		unset($machine_def['bpmn_fragments']);

		$states = array();
		$actions = array();

		// Trivial strategy: Expect all states to be manually named and
		// simply add them to state machines. Basically, swap arrows
		// and tasks to get states and transitions.
		foreach ($bpmn_fragments as $fragment) {
			// Arrows are states
			foreach ($fragment['arrows'] as $a_id => $a) {
				// Register state
				$state_name = trim($a['name']);
				if ($state_name != '') {
					$states[$state_name] = array();
				}
			}

			// Tasks are actions
			foreach ($fragment['nodes'] as $n_id => $n) {
				if ($n['type'] == 'task') {
					$action = trim($n['name']);
					foreach ($n['incoming'] as $in) {
						foreach($n['outgoing']as $out) {
							$in_name = $fragment['arrows'][$in]['name'];
							$out_name = $fragment['arrows'][$out]['name'];
							$actions[$action]['transitions'][$in_name]['targets'][] = $out_name;
						}
					}
				}
			}
		}

		// Update the definition
		$machine_def = array_replace_recursive(array(
				'states' => $states,
				'actions' => $actions,
			), $machine_def);
	}

}

