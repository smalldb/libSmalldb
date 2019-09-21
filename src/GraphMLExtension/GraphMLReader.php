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

namespace Smalldb\StateMachine\GraphMLExtension;

use DOMDocument;
use DOMXpath;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilder;
use Smalldb\StateMachine\Graph\Graph;
use Smalldb\StateMachine\Graph\MissingElementException;


/**
 * GraphML reader
 * Load state machine definition from GraphML created by yEd graph editor.
 * Options:
 *   - `group`: ID of the subdiagram to use. If null, the whole diagram is
 *     used.
 *
 * @see http://www.yworks.com/en/products_yed_about.html
 */
class GraphMLReader
{

	/**
	 * @var StateMachineDefinitionBuilder
	 */
	private $builder;


	/** @var Graph */
	private $graph = null;


	public function __construct(StateMachineDefinitionBuilder $builder)
	{
		$this->builder = $builder;
	}


	public function parseGraphMLFile(string $fileName, ?string $graphml_group_name = null)
	{
		// Load GraphML into DOM
		$dom = new DOMDocument;
		$dom->load($fileName);
		$this->graph = $this->parseDomToGraph($dom, $graphml_group_name);
		return $this->buildStateMachine($this->graph);
	}


	private function parseDomToGraph(DOMDocument $dom, ?string $graphml_group_name = null): Graph
	{
		// Prepare the Graph
		$graph = new Graph();
		$keys = array();

		// Prepare XPath query engine
		$xpath = new DOMXpath($dom);
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
			$keys[$id] = $name;
		}

		// Load graph properties
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
					$graph->setAttr('properties', $properties);
				} else {
					$graph->setAttr($this->str2key($keys[$k]), trim($data_el->textContent));
				}
			}
		}

		// Load nodes
		foreach($xpath->query('.//g:node[@id]', $root_graph) as $el) {
			$id = $el->attributes->getNamedItem('id')->value;
			$node = $graph->createNode($id);
			foreach($xpath->query('.//g:data[@key]', $el) as $data_el) {
				$k = $data_el->attributes->getNamedItem('key')->value;
				if (isset($keys[$k])) {
					$node->setAttr($this->str2key($keys[$k]), $data_el->textContent);
				}
			}
			$label = $xpath->query('.//y:NodeLabel', $el)->item(0)->textContent;
			if ($label !== null) {
				$node->setAttr('label',  trim($label));
			}
			$color = $xpath->query('.//y:Fill', $el)->item(0)->attributes->getNamedItem('color')->value;
			if ($color !== null) {
				$node->setAttr('color', trim($color));
			}
		}

		// Load edges
		foreach($xpath->query('//g:graph/g:edge[@id][@source][@target]') as $el) {
			$id = $el->attributes->getNamedItem('id')->value;
			$source = $el->attributes->getNamedItem('source')->value;
			$target = $el->attributes->getNamedItem('target')->value;
			try {
				$sourceNode = $graph->getNode($source);
				$targetNode = $graph->getNode($target);
			}
			catch (MissingElementException $ex) {
				continue;
			}

			$edge = $graph->createEdge($id, $sourceNode, $targetNode);

			foreach($xpath->query('.//g:data[@key]', $el) as $data_el) {
				$k = $data_el->attributes->getNamedItem('key')->value;
				if (isset($keys[$k])) {
					$edge->setAttr($this->str2key($keys[$k]), $data_el->textContent);
				}
			}
			$label_query_result = $xpath->query('.//y:EdgeLabel', $el)->item(0);
			if (!$label_query_result) {
				throw new GraphMLException(sprintf('Missing edge label. Edge: %s -> %s',
					$sourceNode->getAttr('label', $source),
					$targetNode->getAttr('label', $target)));
			}
			$label = $label_query_result->textContent;
			if ($label !== null) {
				$edge->setAttr('label', trim($label));
			}
			$color_query_result = $xpath->query('.//y:LineStyle', $el)->item(0);
			if ($color_query_result) {
				$color = $color_query_result->attributes->getNamedItem('color')->value;
				if ($color !== null) {
					$edge->setAttr('color', trim($color));
				}
			}
		}

		return $graph;
	}


	private function buildStateMachine(Graph $graph): StateMachineDefinitionBuilder
	{
		// TODO: Attach the original graph to the definition

		// TODO: Process graph attrs.

		// Create states
		foreach ($graph->getNodes() as $n) {
			$stateName = (string) $n->getAttr('label');
			if ($stateName !== '') {
				$state = $this->builder->addState($stateName);
				$state->color = $n->getAttr('color');

				// TODO: Process node attrs.
			}
		}

		// Store actions and transitions
		$transitions = [];
		foreach ($graph->getEdges() as $e) {
			$label = (string) $e->getAttr('label');
			$sourceStateName = (string) $e->getStart()->getAttr('label');
			$targetStateName = (string) $e->getEnd()->getAttr('label');
			if (!$label) {
				throw new GraphMLException(sprintf('Missing label at edge "%s" -> "%s".', $sourceStateName, $targetStateName));
			}

			$transition = $this->builder->getTransition($label, $sourceStateName);
			$transition->targetStates[] = $targetStateName;
			if (($color = $e->getAttr('color')) !== null) {
				$transition->color = $color;
			}

			// TODO: Process edge attrs.
		}

		// Sort stuff to keep them in order when file is modified
		$this->builder->sortPlaceholders();

		return $this->builder;
	}


	/**
	 * Convert some nice property name to key suitable for JSON file.
	 */
	private function str2key($str)
	{
		return strtr(mb_strtolower($str), ' ', '_');
	}


	public function getGraph(): Graph
	{
		return $this->graph;
	}

}

