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

use Smalldb\StateMachine\Graph\DuplicateAttrIndexException;
use Smalldb\StateMachine\Graph\DuplicateEdgeException;
use Smalldb\StateMachine\Graph\DuplicateNodeException;
use Smalldb\StateMachine\Graph\Edge;
use Smalldb\StateMachine\Graph\Graph;
use Smalldb\StateMachine\Graph\Grafovatko\GrafovatkoExporter;
use Smalldb\StateMachine\Graph\GraphSearch;
use Smalldb\StateMachine\Graph\MissingAttrIndexException;
use Smalldb\StateMachine\Graph\MissingEdgeException;
use Smalldb\StateMachine\Graph\MissingElementException;
use Smalldb\StateMachine\Graph\MissingNodeException;
use Smalldb\StateMachine\Graph\Node;


/**
 * Graph Tests
 *
 * TODO: These tests do not cover manipulation with nested graphs.
 *
 */
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


	private function assertNodes(array $expectedNodes, $graph): void
	{
		$this->assertEquals($expectedNodes, array_values($graph instanceof Graph ? $graph->getAllNodes() : $graph));
	}


	private function assertEdges(array $expectedEdges, $graph): void
	{
		$this->assertEquals($expectedEdges, array_values($graph instanceof Graph ? $graph->getAllEdges() : $graph));
	}


	public function testSimpleGraph()
	{
		$g = $this->buildSimpleGraph();
		$this->assertInstanceOf(Graph::class, $g);
		$this->assertEquals(5, count($g->getAllEdges()));
		$this->assertEquals(6, count($g->getAllNodes()));

		$this->assertSame($g, $g->getRootGraph());
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


	public function testDuplicateNode()
	{
		$g = new Graph();
		$g->createNode('A');
		$this->assertTrue($g->hasNode('A'));

		$this->expectException(DuplicateNodeException::class);
		$g->createNode('A');
	}


	public function testDuplicateEdge()
	{
		$g = new Graph();
		$a = $g->createNode('A');
		$b = $g->createNode('B');
		$c = $g->createNode('C');

		$g->createEdge('e1', $a, $b);
		$this->assertTrue($g->hasEdge('e1'));
		$this->expectException(DuplicateEdgeException::class);
		$g->createEdge('e1', $b, $c);
	}


	public function testDuplicateNodeAttrIndex()
	{
		$g = new Graph();
		$g->indexNodeAttr('foo');

		$this->expectException(DuplicateAttrIndexException::class);
		$g->indexNodeAttr('foo');
	}


	public function testDuplicateEdgeAttrIndex()
	{
		$g = new Graph();
		$g->indexEdgeAttr('foo');

		$this->expectException(DuplicateAttrIndexException::class);
		$g->indexEdgeAttr('foo');
	}


	public function testChangeIndexedAttr()
	{
		$g = new Graph();
		$a = $g->createNode('A', ['foo' => 1]);
		$b = $g->createNode('B', ['foo' => 1]);
		$c = $g->createNode('C', ['foo' => 2]);
		$e1 = $g->createEdge(null, $a, $b, ['bar' => 1]);
		$e2 = $g->createEdge(null, $b, $c, ['bar' => 1]);

		$g->indexNodeAttr('foo');
		$this->assertCount(2, $g->getNodesByAttr('foo', 1));

		$b->setAttr('foo', 2);
		$this->assertCount(1, $g->getNodesByAttr('foo', 1));
		$this->assertCount(2, $g->getNodesByAttr('foo', 2));

		$g->indexEdgeAttr('bar');
		$this->assertCount(0, $g->getEdgesByAttr('bar', 2));

		$e2->setAttr('bar', 2);
		$this->assertCount(1, $g->getEdgesByAttr('bar', 2));
	}


	public function testMagicAttr()
	{
		$g = new Graph();
		$a = $g->createNode('A');
		$b = $g->createNode('B', ['foo' => 1]);
		$c = $g->createNode('C', ['foo' => 2]);
		$g->indexNodeAttr('foo');
		$this->assertCount(1, $g->getNodesByAttr('foo', 2));

		$this->assertFalse(isset($a['foo']));
		$this->assertTrue(isset($b['foo']));
		$this->assertEquals(1, $b['foo']);

		$a['foo'] = 2;

		$this->assertTrue(isset($a['foo']));

		$this->assertCount(2, $g->getNodesByAttr('foo', 2));

		unset($a['foo']);

		$this->assertCount(1, $g->getNodesByAttr('foo', 2));
	}


	/**
	 * @dataProvider graphProvider
	 */
	public function testGrafovatkoExport(Graph $g)
	{
		$jsonObject = (new GrafovatkoExporter($g))->export();

		$this->assertNotEmpty($jsonObject['nodes']);
		$this->assertNotEmpty($jsonObject['edges']);

		$this->assertEquals(count($g->getAllNodes()), count($jsonObject['nodes']));
		$this->assertEquals(count($g->getAllEdges()), count($jsonObject['edges']));

	}


	public function testGetNode()
	{
		$g = new Graph();
		$a = $g->createNode('A');
		$b = $g->createNode('B');
		$g->createEdge(null, $a, $b);

		$this->assertSame($a, $g->getNode('A'));

		$this->expectException(MissingElementException::class);
		$g->getNode('X');
	}


	public function testGetNodeById()
	{
		$g = new Graph();
		$a = $g->createNode('A');
		$b = $g->createNode('B');
		$g->createEdge(null, $a, $b);

		$this->assertSame($a, $g->getNodeById('A'));

		$this->expectException(MissingElementException::class);
		$g->getNodeById('X');
	}


	public function testGetEdge()
	{
		$g = new Graph();
		$a = $g->createNode('A');
		$b = $g->createNode('B');
		$e1 = $g->createEdge('e1', $a, $b);

		$this->assertSame($e1, $g->getEdge('e1'));

		$this->expectException(MissingElementException::class);
		$g->getEdge('e2');
	}

	public function testGetEdgeById()
	{
		$g = new Graph();
		$a = $g->createNode('A');
		$b = $g->createNode('B');
		$e1 = $g->createEdge('e1', $a, $b);

		$this->assertSame($e1, $g->getEdgeById('e1'));

		$this->expectException(MissingElementException::class);
		$g->getEdgeById('e2');
	}


	public function testGetNodesByAttr()
	{
		$g = new Graph();
		$g->indexNodeAttr('foo');
		$a = $g->createNode('A', ['foo' => 1]);
		$b = $g->createNode('B', ['foo' => 2]);

		$this->assertNodes([$a], $g->getNodesByAttr('foo', 1));
		$this->assertEdges([$b], $g->getNodesByAttr('foo', 2));

		$this->expectException(MissingAttrIndexException::class);
		$g->getNodesByAttr('bar', 1);
	}


	public function testGetEdgeByAttr()
	{
		$g = new Graph();
		$g->indexEdgeAttr('foo');
		$a = $g->createNode('A');
		$b = $g->createNode('B');
		$e1 = $g->createEdge('e1', $a, $b, ['foo' => 1]);
		$e2 = $g->createEdge('e2', $b, $a, ['foo' => 2]);

		$this->assertEdges([$e1], $g->getEdgesByAttr('foo', 1));
		$this->assertEdges([$e2], $g->getEdgesByAttr('foo', 2));

		$this->expectException(MissingAttrIndexException::class);
		$g->getEdgesByAttr('bar', 1);
	}


	public function testNodeRemove()
	{
		$g = new Graph();
		$a = $g->createNode('A', ['foo' => 1]);
		$b = $g->createNode('B', ['foo' => 2]);
		$c = $g->createNode('C', ['foo' => 2]);
		$e1 = $g->createEdge(null, $a, $b);
		$e2 = $g->createEdge(null, $a, $c);
		$e3 = $g->createEdge(null, $b, $c);
		$g->indexNodeAttr('foo');

		$this->assertCount(3, $g->getAllNodes());
		$this->assertCount(3, $g->getAllEdges());
		$this->assertCount(2, $g->getNodesByAttr('foo', 2));

		$c->remove();

		$this->assertEdges([$e1], $g);
		$this->assertNodes([$a, $b], $g);
		$this->assertCount(1, $g->getNodesByAttr('foo', 2));

		$this->assertEmpty($c->getConnectedEdges());

		$g2 = new Graph();
		$x = $g2->createNode('X');
		$this->expectException(MissingNodeException::class);
		$g->removeNode($x);
	}


	public function testEdgeRemove()
	{
		$g = new Graph();
		$a = $g->createNode('A');
		$b = $g->createNode('B');
		$c = $g->createNode('C');
		$e1 = $g->createEdge(null, $a, $b);
		$e2 = $g->createEdge(null, $a, $c);
		$e3 = $g->createEdge(null, $b, $c);
		$e4 = $g->createEdge(null, $c, $c);

		$this->assertCount(3, $g->getAllNodes());
		$this->assertCount(4, $g->getAllEdges());

		$g->removeEdge($e2);

		$this->assertEdges([$e1, $e3, $e4], $g);
		$this->assertNodes([$a, $b, $c], $g);

		$g->removeEdge($e3);

		$this->assertEdges([$e1, $e4], $g);
		$this->assertNodes([$a, $b, $c], $g);
		$this->assertEdges([$e4], $c->getConnectedEdges());

		$g->removeEdge($e4);

		$this->assertEmpty($c->getConnectedEdges());

		$g2 = new Graph();
		$ee = $g2->createEdge('non-existent-edge', $g2->createNode('X'), $g2->createNode('Y'));
		$this->expectException(MissingEdgeException::class);
		$g->removeEdge($ee);
	}


	public function testReturnEdge()
	{
		$g = new Graph();
		$a = $g->createNode('A');
		$b = $g->createNode('B');
		$e1 = $g->createEdge(null, $a, $b);

		$this->assertEdges([$e1], $a->getConnectedEdges());
		$this->assertEdges([$e1], $b->getConnectedEdges());

		$g->removeEdge($e1);

		$this->assertEmpty($a->getConnectedEdges());
		$this->assertEmpty($b->getConnectedEdges());

		$g->addEdge($e1);

		$this->assertEdges([$e1], $a->getConnectedEdges());
		$this->assertEdges([$e1], $b->getConnectedEdges());
	}


	public function testReconnectEdge()
	{
		$g = new Graph();
		$a = $g->createNode('A');
		$b = $g->createNode('B');
		$c = $g->createNode('C');
		$d = $g->createNode('D');
		$e1 = $g->createEdge(null, $a, $b);

		$this->assertEdges([$e1], $a->getConnectedEdges());
		$this->assertEdges([$e1], $b->getConnectedEdges());
		$this->assertEmpty($c->getConnectedEdges());
		$this->assertEmpty($d->getConnectedEdges());

		$e1->setStart($c);
		$e1->setEnd($d);

		$this->assertEmpty($a->getConnectedEdges());
		$this->assertEmpty($b->getConnectedEdges());
		$this->assertEdges([$e1], $c->getConnectedEdges());
		$this->assertEdges([$e1], $d->getConnectedEdges());
	}

}
