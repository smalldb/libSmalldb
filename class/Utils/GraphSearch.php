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

use Smalldb\StateMachine\InvalidArgumentException;


/**
 * Depth First Search & friends.
 */
class GraphSearch
{
	private $graph;

	private $processNodeCb;
	private $processNodeCbDefault;

	private $checkArrowCb;
	private $checkArrowCbDefault;

	private $strategy;

	const DFS_STRATEGY = 1;
	const BFS_STRATEGY = 2;


	/**
	 * Constructor.
	 */
	private function __construct(Graph $g)
	{
		$this->graph = $g;

		$this->processNodeCb
			= $this->processNodeCbDefault
			= function(& $current_node) { return true; };

		$this->checkArrowCb
			= $this->checkArrowCbDefault
			= function(& $current_node, & $arrow, & $next_node, $next_node_seen) { return true; };
	}


	public static function DFS(Graph $g): self
	{
		$gs = new self($g);
		$gs->strategy = self::DFS_STRATEGY;
		return $gs;
	}


	public static function BFS(Graph $g): self
	{
		$gs = new self($g);
		$gs->strategy = self::BFS_STRATEGY;
		return $gs;
	}


	/**
	 * Call $func when entering the node.
	 *
	 * @param callable $callback function($current_node_id)
	 */
	public function onNode(callable $callback): self
	{
		$this->processNodeCb = $callback ? : $this->processNodeCbDefault;
		return $this;
	}


	/**
	 * Call $func when inspecting next nodes, before enqueuing them.
	 * Nodes are inspected always, even when they have been seen before,
	 * but once seen nodes are not enqueued again.
	 *
	 * @param callable $callback function($current_node_id, $next_node_id, $next_node_seen, $arrow_id)
	 */
	public function onArrow(callable $callback): self
	{
		$this->checkArrowCb = $callback ? : $this->checkArrowCb;
		return $this;
	}


	/**
	 * Start DFS from $startNodes.
	 *
	 * @param array $startNodes List of starting nodes or their IDs.
	 */
	public function start(array $startNodes)
	{
		$queue = [];	// Sometimes, it is a stack ;)
		$seen = [];

		$processNodeCb = $this->processNodeCb;
		$checkArrowCb = $this->checkArrowCb;

		// Enqueue nodes as mark them seen
		foreach ($startNodes as $node) {
			$id = is_scalar($node) ? $node : $node['id'];
			$seen[$id] = true;
			$queue[] = $id;
		}

		// Process queue
		while (!empty($queue)) {
			// get next node
			switch ($this->strategy) {
				case self::DFS_STRATEGY:
					$currentNodeId = array_pop($queue);
					break;
				case self::BFS_STRATEGY:
					$currentNodeId = array_shift($queue);
					break;
				default:
					throw new InvalidArgumentException('Invalid strategy.');
			}
			$seen[$currentNodeId] = true;

			// Process node
			if (!$processNodeCb($this->graph->getNode($currentNodeId))) {
				continue;
			}

			// add next nodes to queue
			$arrows = $this->graph->getArrowsByNode($currentNodeId);

			foreach ($arrows as $arrow) {
				$nextNodeId = $arrow['target'];

				// Check next node whether it is worth processing
				$next_node_seen = !empty($seen[$nextNodeId]);
				if ($checkArrowCb($this->graph->getNode($currentNodeId), $arrow, $this->graph->getNode($nextNodeId), $next_node_seen)) {
					if (!$next_node_seen) {
						$queue[] = $nextNodeId;
					}
				}
				$seen[$nextNodeId] = true;
			}
		}
	}

}

