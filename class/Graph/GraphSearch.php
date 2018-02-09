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

namespace Smalldb\StateMachine\Graph;

use Smalldb\StateMachine\InvalidArgumentException;


/**
 * Depth First Search & friends.
 */
class GraphSearch
{
	private $graph;

	private $processNodeCb;
	private $processNodeCbDefault;

	private $checkEdgeCb;
	private $checkArrowCbDefault;

	private $strategy;
	private $direction = self::DIR_FORWARD;

	const DFS_STRATEGY = 0x01;
	const BFS_STRATEGY = 0x02;

	const DIR_FORWARD = 0x10;
	const DIR_BACKWARD = 0x20;
	const DIR_BOTH = 0x30;


	/**
	 * Constructor.
	 */
	private function __construct(NestedGraph $g)
	{
		$this->graph = $g;

		$this->processNodeCb
			= $this->processNodeCbDefault
			= function(Node $current_node) { return true; };

		$this->checkEdgeCb
			= $this->checkArrowCbDefault
			= function(Node $current_node, Edge $edge, Node $next_node, bool $next_node_seen) { return true; };
	}


	public static function DFS(NestedGraph $g): self
	{
		$gs = new self($g);
		$gs->strategy = self::DFS_STRATEGY;
		return $gs;
	}


	public static function BFS(NestedGraph $g): self
	{
		$gs = new self($g);
		$gs->strategy = self::BFS_STRATEGY;
		return $gs;
	}


	public function runForward(): self
	{
		$this->direction = self::DIR_FORWARD;
		return $this;
	}


	public function runBackward(): self
	{
		$this->direction = self::DIR_BACKWARD;
		return $this;
	}


	public function runBothWays(): self
	{
		$this->direction = self::DIR_BOTH;
		return $this;
	}


	/**
	 * Call $func when entering the node.
	 *
	 * @param callable $callback function(Node $current_node)
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
	 * @param callable $callback function(Node $current_node, Edge $edge, Node $next_node, bool $next_node_seen)
	 */
	public function onEdge(callable $callback): self
	{
		$this->checkEdgeCb = $callback ? : $this->checkEdgeCb;
		return $this;
	}


	/**
	 * Start DFS from $startNodes.
	 *
	 * @param Node[] $startNodes List of starting nodes or their IDs.
	 */
	public function start(array $startNodes)
	{
		$queue = [];	// Sometimes, it is a stack ;)
		$seen = [];

		$processNodeCb = $this->processNodeCb;
		$checkEdgeCb = $this->checkEdgeCb;

		// Enqueue nodes as mark them seen
		foreach ($startNodes as $i => $node) {
			if ($node instanceof Node) {
				$id = $node->getId();
				$seen[$id] = true;
				$queue[] = $node;
			} else {
				throw new \InvalidArgumentException("Start node ".var_export($i)." is not instance of Node.");
			}
		}

		// Process queue
		while (!empty($queue)) {
			/** @var Node $currentNode */
			// get next node
			switch ($this->strategy) {
				case self::DFS_STRATEGY:
					$currentNode = array_pop($queue);
					break;
				case self::BFS_STRATEGY:
					$currentNode = array_shift($queue);
					break;
				default:
					throw new InvalidArgumentException('Invalid strategy.');
			}
			$seen[$currentNode->getId()] = true;

			// Process node
			if (!$processNodeCb($currentNode)) {
				continue;
			}

			// add next nodes to queue
			$edges = $currentNode->getConnectedEdges();

			foreach ($edges as $edge) {
				if (($this->direction & self::DIR_FORWARD) && $edge->getStart() === $currentNode) {
					// Forward edges
					$nextNode = $edge->getEnd();
				} else if (($this->direction & self::DIR_BACKWARD) && $edge->getEnd() === $currentNode) {
					// Backward edges
					$nextNode = $edge->getStart();
				} else {
					// Ignore edges of undesired direction.
					continue;
				}

				$nextNodeId = $nextNode->getId();

				// Check next node whether it is worth processing
				$next_node_seen = !empty($seen[$nextNodeId]);
				if ($checkEdgeCb($currentNode, $edge, $nextNode, $next_node_seen)) {
					if (!$next_node_seen) {
						$queue[] = $nextNode;
					}
				}
				$seen[$nextNodeId] = true;
			}
		}
	}

}

