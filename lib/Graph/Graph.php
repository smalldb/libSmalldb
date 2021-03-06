<?php declare(strict_types = 1);
/*
 * Copyright (c) 2018, Josef Kufner  <josef@kufner.cz>
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


namespace Smalldb\Graph;


class Graph extends NestedGraph
{

	public function __construct(array $attrs = [])
	{
		parent::__construct(null, $attrs);
		$this->rootGraph = $this;

		$this->nodeAttrIndex = new ElementAttrIndex(Node::class);
		$this->edgeAttrIndex = new ElementAttrIndex(Edge::class);
	}


	public function createNestedGraph(Node $parentNode): NestedGraph
	{
		return new NestedGraph($parentNode);
	}


	public function getNodeById(string $id): Node
	{
		$node = $this->nodeAttrIndex->getElementById($id);
		if ($node instanceof Node) {
			return $node;
		} else {
			throw new \LogicException("The requested element is not a node: $id"); //@codeCoverageIgnore
		}
	}


	public function getEdgeById(string $id): Edge
	{
		$edge = $this->edgeAttrIndex->getElementById($id);
		if ($edge instanceof Edge) {
			return $edge;
		} else {
			throw new \LogicException("The requested element is not an edge: $id"); //@codeCoverageIgnore
		}
	}

	/**
	 * Get all nodes in the graph, including nodes in nested graphs.
	 *
	 * @return Node[]
	 */
	public function getAllNodes(): array
	{
		/** @var Node[] $nodes */
		$nodes = $this->nodeAttrIndex->getAllElements();
		return $nodes;
	}


	/**
	 * Get all edges in the graph, including edges in nested graphs.
	 *
	 * @return Edge[]
	 */
	public function getAllEdges(): array
	{
		/** @var Edge[] $edges */
		$edges = $this->edgeAttrIndex->getAllElements();
		return $edges;
	}


	/**
	 * Get nodes which have attribute $key equal to $value
	 *
	 * @param string $key
	 * @param mixed $value
	 * @return Node[]
	 */
	public function getNodesByAttr(string $key, $value = true): array
	{
		return $this->nodeAttrIndex->getElements($key, $value);
	}


	/**
	 * Get edges which have attribute $key equal to $value
	 *
	 * @param string $key
	 * @param mixed $value
	 * @return Node[]
	 */
	public function getEdgesByAttr(string $key, $value = true): array
	{
		return $this->edgeAttrIndex->getElements($key, $value);
	}

	public function indexNodeAttr($key): NestedGraph
	{
		$this->nodeAttrIndex->createAttrIndex($key);
		return $this;
	}

	public function indexEdgeAttr($key): NestedGraph
	{
		$this->edgeAttrIndex->createAttrIndex($key);
		return $this;
	}

}
