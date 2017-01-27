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
 * GraphML reader
 *
 * Load state machine definition from GraphML created by yEd graph editor.
 *
 * Options:
 *
 *   - `group`: ID of the subdiagram to use. If null, the whole diagram is
 *     used.
 *
 * @see http://www.yworks.com/en/products_yed_about.html
 */
class GraphMLReader implements IMachineDefinitionReader
{

	/// @copydoc IMachineDefinitionReader::loadString
	public static function loadString($machine_type, $data_string, $options = array(), $filename = null)
	{
		// Options
		$graphml_group_name = isset($options['group']) ? $options['group'] : null;

		// Graph
		$keys = array();
		$nodes = array();
		$edges = array();

		// Load GraphML into DOM
		$dom = new \DOMDocument;
		$dom->loadXml($data_string);

		// Prepare XPath query engine
		$xpath = new \DOMXpath($dom);
		$xpath->registerNameSpace('g', 'http://graphml.graphdrawing.org/xmlns');

		// Find group node
		if ($graphml_group_name) {
			$root_graph = null;
			foreach($xpath->query('//g:graph') as $el) {
				foreach($xpath->query('../g:data/*/*/y:GroupNode/y:NodeLabel', $el) as $label_el) {
					$label = trim($label_el->textContent);

					if ($label == $graphml_group_name) {
						$root_graph = $el;
						break 2;
					}
				}
			}
		} else {
			$root_graph = $xpath->query('/g:graphml/g:graph')->item(0);
		}
		if ($root_graph == null) {
			throw new GraphMLException('Graph node not found.');
		}

		// Load keys
		foreach($xpath->query('./g:key[@attr.name][@id]') as $el) {
			$id = $el->attributes->getNamedItem('id')->value;
			$name = $el->attributes->getNamedItem('attr.name')->value;
			//debug_msg("tag> %s => %s", $id, $name);
			$keys[$id] = $name;
		}

		// Load graph properties
		$graph_props = array();
		foreach($xpath->query('./g:data[@key]', $root_graph) as $data_el) {
			$k = $data_el->attributes->getNamedItem('key')->value;
			if (isset($keys[$k])) {
				if ($keys[$k] == 'Properties') {
					// Special handling of machine properties
					$properties = array();
					foreach ($xpath->query('./property[@name]', $data_el) as $property_el) {
						$property_name = $property_el->attributes->getNamedItem('name')->value;
						foreach ($property_el->attributes as $property_attr_name => $property_attr) {
							$properties[$property_name][$property_attr_name] = $property_attr->value;
						}
					}
					$graph_props['properties'] = $properties;
				} else {
					$graph_props[static::str2key($keys[$k])] = trim($data_el->textContent);
				}
			}
		}
		//debug_dump($graph_props, '$graph_props');

		// Load nodes
		foreach($xpath->query('.//g:node[@id]', $root_graph) as $el) {
			$id = $el->attributes->getNamedItem('id')->value;
			$node_props = array();
			foreach($xpath->query('.//g:data[@key]', $el) as $data_el) {
				$k = $data_el->attributes->getNamedItem('key')->value;
				if (isset($keys[$k])) {
					$node_props[static::str2key($keys[$k])] = $data_el->textContent;
				}
			}
			$label = $xpath->query('.//y:NodeLabel', $el)->item(0)->textContent;
			if ($label !== null) {
				$node_props['label'] = trim($label);
			}
			$color = $xpath->query('.//y:Fill', $el)->item(0)->attributes->getNamedItem('color')->value;
			if ($color !== null) {
				$node_props['color'] = trim($color);
			}
			//debug_msg("node> %s: \"%s\"", $id, @ $node_props['state']);
			//debug_dump($node_props, '$node_props');
			$nodes[$id] = $node_props;
		}

		// Load edges
		foreach($xpath->query('//g:graph/g:edge[@id][@source][@target]') as $el) {
			$id = $el->attributes->getNamedItem('id')->value;
			$source = $el->attributes->getNamedItem('source')->value;
			$target = $el->attributes->getNamedItem('target')->value;
			if (!isset($nodes[$source]) || !isset($nodes[$target])) {
				continue;
			}
			$edge_props = array();
			foreach($xpath->query('.//g:data[@key]', $el) as $data_el) {
				$k = $data_el->attributes->getNamedItem('key')->value;
				if (isset($keys[$k])) {
					$edge_props[static::str2key($keys[$k])] = $data_el->textContent;
				}
			}
			$label_query_result = $xpath->query('.//y:EdgeLabel', $el)->item(0);
			if (!$label_query_result) {
				throw new GraphMLException(sprintf('Missing edge label. Edge: %s -> %s',
					isset($nodes[$source]['label']) ? $nodes[$source]['label'] : $source,
					isset($nodes[$target]['label']) ? $nodes[$target]['label'] : $target));
			}
			$label = $label_query_result->textContent;
			if ($label !== null) {
				$edge_props['label'] = trim($label);
			}
			$color_query_result = $xpath->query('.//y:LineStyle', $el)->item(0);
			if ($color_query_result) {
				$color = $color_query_result->attributes->getNamedItem('color')->value;
				if ($color !== null) {
					$edge_props['color'] = trim($color);
				}
			}
			//debug_msg("edge> %s: %s -> %s", $id, $source, $target);
			//debug_dump($edge_props, '$edge_props');
			$edges[$id] = array($source, $target, $edge_props);
		}

		// Build machine definition
		$machine = array('_' => "<?php printf('_%c%c}%c',34,10,10);__halt_compiler();?>");

		// Graph properties
		foreach($graph_props as $k => $v) {
			$machine[$k] = $v;
		}

		// Store states
		foreach ($nodes as & $n) {
			if (empty($n['state'])) {
				if (empty($n['label'])) {
					// Skip 'nonexistent' state, it is present by default
					$n['state'] = '';
					continue;
				} else {
					// Use label as state name
					$n['state'] = (string) $n['label'];
				}
			}
			if (empty($n['label'])) {
				$n['label'] = $n['state'];
			}
			$machine['states'][(string) $n['state']] = $n;
		}

		// Store actions and transitions
		foreach ($edges as $e) {
			list($source_id, $target_id, $props) = $e;
			$source = $source_id != '' ? (isset($nodes[$source_id]) ? (string) $nodes[$source_id]['state'] : null) : '';
			$target = $target_id != '' ? (isset($nodes[$target_id]) ? (string) $nodes[$target_id]['state'] : null) : '';
			if ($source === null || $target === null) {
				// Ignore nonexistent nodes
				continue;
			}
			if (@ $props['action'] != '') {
				$action = $props['action'];
			} else if (@ $props['label'] != '') {
				$action = $props['label'];
			} else {
				throw new GraphMLException(sprintf('Missing label at edge "%s" -> "%s".',
					$nodes[$source]['label'], $nodes[$target]['label']));
			}

			$tr = & $machine['actions'][$action]['transitions'][$source];
			foreach ($props as $k => $v) {
				$tr[$k] = $v;
			}
			$tr['targets'][] = $target;
			unset($tr);
		}

		// Sort stuff to keep them in order when file is modified
		asort($machine['states']);
		asort($machine['actions']);
		//debug_dump($machine['states'], 'States');
		//debug_dump($machine['actions'], 'Actions');

		//debug_dump($machine, '$machine');
		return $machine;
	}


	/// @copydoc IMachineDefinitionReader::postprocessDefinition
	public static function postprocessDefinition($machine_type, & $machine_def)
	{
		// NOP.
	}


	/**
	 * Convert some nice property name to key suitable for JSON file.
	 */
	private static function str2key($str)
	{
		return strtr(mb_strtolower($str), ' ', '_');
	}

}

