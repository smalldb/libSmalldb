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

/**
 * Depth First Search.
 */
class DepthFirstSearch
{

	private $processNodeCb;
	private $processNodeCbDefault;

	private $checkNextNodeCb;
	private $checkNextNodeCbDefault;


	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->processNodeCb
			= $this->processNodeCbDefault
			= function($current_node_id) {};

		$this->checkNextNodeCb
			= $this->checkNextNodeCbDefault
			= function($current_node_id, $next_node_id, $next_node_seen) { return true; };
	}


	/**
	 * Call $func when entering the node.
	 *
	 * @param $callback function($current_node_id)
	 */
	public function onProcessNode(callable $callback)
	{
		$this->processNodeCb = $callback ? : $this->processNodeCbDefault;
		return $this;
	}


	/**
	 * Call $func when inspecting next nodes, before enqueuing them.
	 *
	 * Nodes are inspected always, even when they have been seen before,
	 * but once seen nodes are not enqueued again.
	 *
	 * @param $callback function($current_node_id, $next_node_id, $next_node_seen)
	 * @return True if the $next_node_id should be engueued for processing.
	 * 	The node will not be enqueued if it was already visited.
	 */
	public function onCheckNextNode(callable $callback)
	{
		$this->checkNextNodeCb = $callback ? : $this->checkNextNodeCb;
		return $this;
	}


	/**
	 * Start DFS from $startNodes.
	 *
	 * @param $startNodes List of starting nodes.
	 * @param $nextNodes Map $current_node_id -> list of $next_nodes.
	 */
	public function start($start_nodes, $next_nodes)
	{
		$queue = [];	// It is a stack ;)
		$seen = [];

		$processNodeCb = $this->processNodeCb;
		$checkNextNodeCb = $this->checkNextNodeCb;

		// Enqueue nodes as mark them seen
		foreach ($start_nodes as $n) {
			$seen[$n] = true;
			$queue[] = $n;
		}

		// Process queue
		while (!empty($queue)) {
			$current_node_id = array_pop($queue);

			// Process node
			$processNodeCb($current_node_id);

			// add next nodes to queue
			if (isset($next_nodes[$current_node_id])) {
				foreach ($next_nodes[$current_node_id] as $next_node_id) {
					// Check next node whether it is worth processing
					$next_node_seen = !empty($seen[$next_node_id]);
					if ($checkNextNodeCb($current_node_id, $next_node_id, $next_node_seen)) {
						if (!$next_node_seen) {
							$queue[] = $next_node_id;
						}
					}
					$seen[$next_node_id] = true;
				}
			}
		}
	}

}

