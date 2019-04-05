<?php declare(strict_types = 1);
/*
 * Copyright (c) 2019, Josef Kufner  <josef@kufner.cz>
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
namespace Smalldb\StateMachine\Test;

use PHPUnit\Framework\TestCase;
use Smalldb\StateMachine\Graph\Edge;
use Smalldb\StateMachine\Graph\Graph;
use Smalldb\StateMachine\Graph\GraphExportGrafovatko;
use Smalldb\StateMachine\Graph\GraphSearch;
use Smalldb\StateMachine\Graph\Node;

class GraphTest extends TestCase
{
	private function buildSimpleGraph(): Graph
	{
		$g = new Graph();

		$a = $g->createNode('A');
		$b = $g->createNode('B');
		$c = $g->createNode('C');
		$d = $g->createNode('D');
		$e = $g->createNode('E');
		$f = $g->createNode('F');

		$g->createEdge(null, $a, $b);
		$g->createEdge(null, $b, $c);
		$g->createEdge(null, $c, $d);
		$g->createEdge(null, $d, $e);
		$g->createEdge(null, $e, $f);

		return $g;
	}

	private function buildSimpleCircularGraph(): Graph
	{
		$g = $this->buildSimpleGraph();
		$g->createEdge(null, $g->getNodeById('D'), $g->getNodeById('A'));
		return $g;
	}

	public function testSimpleGraph()
	{
		$g = $this->buildSimpleGraph();
		$this->assertInstanceOf(Graph::class, $g);
		$this->assertEquals(5, count($g->getAllEdges()));
		$this->assertEquals(6, count($g->getAllNodes()));
	}

	/**
	 * @dataProvider graphProvider
	 */
	public function testSimpleReachability(Graph $g)
	{
		$visitedNodes = [];

		$actualNodesCount = count($g->getAllNodes());

		GraphSearch::DFS($g)
			->runForward()
			->onNode(function(Node $node) use (&$visitedNodes): bool {
				$id = $node->getId();
				$this->assertEmpty($visitedNodes[$id] ?? null);
				$visitedNodes[$id] = $node;
				return $id != 'E';
			})
			->start([$g->getNodeById('A')]);

		// We don't go to node F.
		$expectedNodesCount = $actualNodesCount - 1;
		$this->assertEmpty($visitedNodes['F'] ?? null);

		// But we should have visited all other nodes
		$visitedNodesCount = count($visitedNodes);
		$this->assertEquals($expectedNodesCount, $visitedNodesCount, "Only $visitedNodesCount of $expectedNodesCount nodes visited.");
	}

	/**
	 * @dataProvider graphProvider
	 */
	public function testBackwardsReachability(Graph $g)
	{
		$visitedNodes = [];
		$visitedEdgeCount = 0;

		GraphSearch::BFS($g)
			->runBackward()
			->onNode(function(Node $node) use (&$visitedNodes): bool {
				$id = $node->getId();
				$this->assertEmpty($visitedNodes[$id] ?? null);
				$visitedNodes[$id] = $node;
				return true;
			})
			->onEdge(function(Node $current_node, Edge $edge, Node $next_node, bool $next_node_seen) use (&$visitedEdgeCount): bool {
				$visitedEdgeCount++;
				return true;
			})
			->start([$g->getNodeById('D')]);

		// We should visit some edges
		$this->assertGreaterThanOrEqual(count($visitedNodes) - 1, $visitedEdgeCount);

		// We don't go to node F, because it is unreachable when running backwards.
		$this->assertEmpty($visitedNodes['F'] ?? null, 'Node F reached.');

		// We should find node 'A'.
		$this->assertNotEmpty($visitedNodes['A'] ?? null, 'Node A not reached.');
	}

	/**
	 * @dataProvider graphProvider
	 */
	public function testBothWayReachability(Graph $g)
	{
		$visitedNodes = [];

		GraphSearch::DFS($g)
			->runBothWays()
			->onNode(function(Node $node) use (&$visitedNodes): bool {
				$id = $node->getId();
				$this->assertEmpty($visitedNodes[$id] ?? null);
				$visitedNodes[$id] = $node;
				return true;
			})
			->start([$g->getNodeById('D')]);

		// Node F is reachable when going both ways
		$this->assertNotEmpty($visitedNodes['F'] ?? null, 'Node F not reached.');

		// We should find node 'A' too.
		$this->assertNotEmpty($visitedNodes['A'] ?? null, 'Node A not reached.');
	}

	public function graphProvider()
	{
		yield 'Simple' => [$this->buildSimpleGraph()];
		yield 'Circular' => [$this->buildSimpleCircularGraph()];
	}


	/**
	 * @dataProvider graphProvider
	 */
	public function testSearchFromBadStart(Graph $g)
	{
		$this->expectException(\InvalidArgumentException::class);
		GraphSearch::BFS($g)->start(['X']);
	}


	/**
	 * @dataProvider graphProvider
	 */
	public function testGrafovatkoExport(Graph $g)
	{
		$export = new GraphExportGrafovatko($g);
		$jsonObject = $export->export();

		$this->assertNotEmpty($jsonObject['nodes']);
		$this->assertNotEmpty($jsonObject['edges']);

		$this->assertEquals(count($g->getAllNodes()), count($jsonObject['nodes']));
		$this->assertEquals(count($g->getAllEdges()), count($jsonObject['edges']));

	}

}
