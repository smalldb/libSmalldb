<?php
/*
 * Copyright (c) 2017, Josef Kufner  <josef@kufner.cz>
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

namespace Smalldb\StateMachine\Utils;

class Graph
{

	/// Nodes - source data
	protected $nodes;
	/// Arrows - source data
	protected $arrows;

	/// Arrows (refs) starting at given node - calculated
	protected $arrows_by_node;

	/// Nodes by type - calculated
	protected $nodes_by_type;

	/// Indexed tags for nodes
	protected $node_tags = [];

	/// Indexed tags for arrows
	protected $arrow_tags = [];


	/**
	 * Constructor.
	 *
	 * @param &$nodes List of nodes indexed by id. Each node
	 * 	is a key-value array: id, type.
	 * @param &$arrows List of arrows indexed by id. Each arrow
	 * 	is a key-value array: id, source, target.
	 * @param $node_tags List of tags (keys) which should be indexed on $nodes.
	 * @param $arrow_tags List of tags (keys) which should be indexed on $arrows.
	 */
	public function __construct(& $nodes, & $arrows, $node_tags = [], $arrow_tags = [])
	{
		$this->nodes = & $nodes;
		$this->arrows = & $arrows;
		$this->node_tags = array_fill_keys($node_tags, []);
		$this->arrow_tags = array_fill_keys($arrow_tags, []);
		$this->recalculateGraph();
	}


	/**
	 * Update effective representations of the graph.
	 *
	 * Call this when graph topology is changed.
	 */
	public function recalculateGraph()
	{
		$this->arrows_by_node = [];
		$this->arrows_by_node_inv = [];
		$this->arrow_tags = array_fill_keys(array_keys($this->arrow_tags), []);
		foreach ($this->arrows as $id => & $a) {
			$this->arrows_by_node[$a['source']][$id] = & $a;
			$this->arrows_by_node_inv[$a['target']][$id] = & $a;
			foreach ($this->arrow_tags as $tag => & $tag_arrows) {
				if (!empty($a[$tag])) {
					$tag_arrows[$id] = & $a;
				}
			}
		}

		$this->nodes_by_type = [];
		$this->node_tags = array_fill_keys(array_keys($this->node_tags), []);
		foreach ($this->nodes as $id => & $n) {
			$this->nodes_by_type[$n['type']][$id] = & $n;
			foreach ($this->node_tags as $tag => & $tag_nodes) {
				if (!empty($n[$tag])) {
					$tag_nodes[$id] = & $n;
				}
			}
		}

	}


	public function & getNode($node_id)
	{
		if (!isset($this->nodes[$node_id])) {
			throw new \InvalidArgumentException('Unknown node: ' . $node_id);
		}
		return $this->nodes[$node_id];
	}


	public function & getArrow($arrow_id)
	{
		if (!isset($this->arrows[$arrow_id])) {
			throw new \InvalidArgumentException('Unknown arrow: ' . $arrow_id);
		}
		return $this->arrows[$arrow_id];
	}


	/**
	 * Get nodes by type
	 *
	 * @return List of references to $nodes.
	 */
	public function getNodesByType($type)
	{
		return $this->nodes_by_type[$type];
	}


	/**
	 * Get arrows starting from $node
	 *
	 * @return List of references to $arrows.
	 */
	public function getArrowsByNode($node)
	{
		$node_id = is_scalar($node) ? $node : $node['id'];
		return isset($this->arrows_by_node[$node_id]) ? $this->arrows_by_node[$node_id] : [];
	}


	/**
	 * Get arrows ending in $node
	 *
	 * @return List of references to $arrows.
	 */
	public function getArrowsByTargetNode($node)
	{
		$node_id = is_scalar($node) ? $node : $node['id'];
		return isset($this->arrows_by_node_inv[$node_id]) ? $this->arrows_by_node_inv[$node_id] : [];
	}


	/**
	 * Set tag on the node.
	 */
	public function tagNode($node, $tag, $tagged = true)
	{
		if (!isset($tag)) {
			throw new \InvalidArgumentException('Unknown node tag: ' . $tag);
		}
		$node_id = is_scalar($node) ? $node : $node['id'];
		$this->nodes[$node_id][$tag] = (bool) $tagged;
		if ($tagged) {
			$this->node_tags[$tag][$node_id] = & $this->nodes[$node_id];
		} else {
			unset($this->node_tags[$tag][$node_id]);
		}
	}


	/**
	 * Set tag on the arrow.
	 */
	public function tagArrow($arrow, $tag, $tagged = true)
	{
		if (!isset($tag)) {
			throw new \InvalidArgumentException('Unknown arrow tag: ' . $tag);
		}
		$arrow_id = is_scalar($arrow) ? $arrow : $arrow['id'];
		$this->arrows[$arrow_id][$tag] = (bool) $tagged;
		if ($tagged) {
			$this->arrow_tags[$tag][$arrow_id] = & $this->arrows[$arrow_id];
		} else {
			unset($this->arrow_tags[$tag][$arrow_id]);
		}
	}


	/**
	 * Get tagged nodes.
	 */
	public function getNodesByTag($tag)
	{
		return $this->node_tags[$tag];
	}

	/**
	 * Get tagged arrows.
	 */
	public function getArrowsByTag($tag)
	{
		return $this->arrow_tags[$tag];
	}


}

