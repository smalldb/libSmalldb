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

class Graph
{

	/**
	 * Nodes
	 *
	 * @var Node[]
	 */
	private $nodes = [];

	/**
	 * @var Edge[]
	 */
	private $edges = [];

	/**
	 * Node index
	 */
	private $nodeAttrIndex = [];

	/**
	 * Edge index
	 */
	private $edgeAttrIndex = [];


	/**
	 * Constructor.
	 */
	public function __construct()
	{
	}


	/**
	 * Get all nodes in the graph
	 *
	 * @return Node[]
	 */
	public function getNodes(): array
	{
		return $this->nodes;
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
		return $this->getElementFromAttrIndex($this->nodeAttrIndex, $key, $value);
	}


	/**
	 * Get all edges in the graph
	 *
	 * @return Edge[]
	 */
	public function getEdges(): array
	{
		return $this->edges;
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
		return $this->getElementFromAttrIndex($this->edgeAttrIndex, $key, $value);
	}


	/**
	 * Create node and add it to the graph.
	 *
	 * @param string $id
	 * @param array $attrs
	 * @return Node
	 */
	public function createNode(string $id, array $attrs): Node
	{
		return new Node($this, $id, $attrs);
	}


	/**
	 * Add existing node to the graph.
	 *
	 * @param Node $node
	 * @return Graph
	 */
	public function addNode(Node $node): self
	{
		$id = $node->getId();

		if (isset($this->nodes[$id])) {
			throw new DuplicateNodeException(sprintf("Node \"%s\" already present in the graph.", $id));
		}

		// Add element
		$this->nodes[$id] = $node;

		// Update attr indexes
		$this->insertElementIntoAttrIndex($this->nodeAttrIndex, $node);

		return $this;
	}


	/**
	 * Get node by its ID.
	 *
	 * @param string $id
	 * @return Node
	 */
	public function getNode(string $id): Node
	{
		if (isset($this->nodes[$id])) {
			return $this->nodes[$id];
		} else {
			throw new MissingNodeException(sprintf("Node \"%s\" not found in the graph.", $id));
		}
	}


	/**
	 * Remove node and connected edges from the graph.
	 *
	 * @param Node $node
	 * @return Graph
	 */
	public function removeNode(Node $node): self
	{
		$id = $node->getId();
		if (isset($this->nodes[$id])) {
			foreach ($node->getConnectedEdges() as $e) {
				$e->remove();
			}
			unset($this->nodes[$id]);

			// TODO: Update node index

			return $this;
		} else {
			throw new MissingEdgeException(sprintf("Node \"%s\" not found in the graph.", $id));
		}
	}



	/**
	 * Create a new edge and add it to the graph.
	 *
	 * @param string|null $id ID of the edge, generated automatically if null.
	 * @param array $attrs Key-value storage of the edge attributes.
	 * @param Node $start
	 * @param Node $end
	 * @return Edge
	 */
	public function createEdge(string $id = null, Node $start, Node $end, array $attrs): Edge
	{
		if ($id === null) {
			$id = '_' . count($this->edges);
		}

		return new Edge($this, $id, $start, $end, $attrs);
	}


	/**
	 * Add existing edge to the graph.
	 *
	 * @param Edge $edge
	 * @return Graph
	 */
	public function addEdge(Edge $edge): self
	{
		$id = $edge->getId();

		if (isset($this->edges[$id])) {
			throw new DuplicateEdgeException(sprintf("Edge \"%s\" already present in the graph.", $id));
		}

		// Add element
		$this->edges[$id] = $edge;

		// Update attr indexes
		$this->insertElementIntoAttrIndex($this->edgeAttrIndex, $edge);

		return $this;
	}


	/**
	 * Get Edge by its ID.
	 *
	 * @param string $id
	 * @return Edge
	 */
	public function getEdge(string $id): Edge
	{
		if (isset($this->edges[$id])) {
			return $this->edges[$id];
		} else {
			throw new MissingEdgeException(sprintf("Edge \"%s\" not found in the graph.", $id));
		}
	}


	/**
	 * Remove edge from the graph and disconnect it from nodes.
	 *
	 * @param Edge $edge
	 * @return Graph
	 */
	public function removeEdge(Edge $edge): self
	{
		$id = $edge->getId();
		if (isset($this->edges[$id])) {
			$this->edges[$id]->disconnectNodes();
			unset($this->edges[$id]);

			// TODO: Update edge index

			return $this;
		} else {
			throw new MissingEdgeException(sprintf("Edge \"%s\" not found in the graph.", $id));
		}
	}



	public function createNodeAttrIndex($key): self
	{
		$this->createAttrIndex($this->nodeAttrIndex, $key);
		$this->rebuildAttrIndex($this->nodeAttrIndex, $key, $this->getNodes());
		return $this;
	}


	public function createEdgeAttrIndex($key): self
	{
		$this->createAttrIndex($this->edgeAttrIndex, $key);
		$this->rebuildAttrIndex($this->edgeAttrIndex, $key, $this->getNodes());
		return $this;
	}


	private function createAttrIndex(array & $index, string $key)
	{
		if (isset($index[$key])) {
			throw new DuplicateAttrIndexException("Attribute index \"$key\" already exists.");
		}
		$index[$key] = [];
	}


	/**
	 * @param array $index
	 * @param string $key
	 * @param AbstractElement[] $elements
	 */
	private function rebuildAttrIndex(array & $index, string $key, array $elements)
	{
		if (!isset($index[$key])) {
			throw new MissingAttrIndexException("Attribute index \"$key\" is not defined.");
		}

		$index[$key] = [];

		foreach ($elements as $id => $element) {
			$value = $element->getAttr($key);
			$index[$key][$value][$id] = $element;
		}
	}


	/**
	 * Insert element into index (all keys has changed).
	 */
	private function insertElementIntoAttrIndex(array & $index, AbstractElement $element)
	{
		$id = $element->getId();
		$indexedAttrs = array_intersect_key($element->getAttributes(), $index);
		foreach ($indexedAttrs as $key => $newValue) {
			$index[$key][$newValue][$id] = $element;
		}
	}

	/**
	 * Remove element from index
	 */
	private function removeElementFromAttrIndex(array & $index, AbstractElement $element)
	{
		$id = $element->getId();
		$indexedAttrs = array_intersect_key($element->getAttributes(), $index);
		foreach ($indexedAttrs as $key => $oldValue) {
			unset($index[$key][$oldValue][$id]);
		}
	}


	private function updateAttrIndex(array & $index, string $key, $oldValue, $newValue, AbstractElement $element)
	{
		if (!isset($index[$key])) {
			throw new MissingAttrIndexException("Attribute index \"$key\" is not defined.");
		}

		$id = $element->getId();

		if (isset($index[$key][$oldValue][$id])) {
			unset($index[$key][$oldValue][$id]);
		}
		$index[$key][$newValue][$id] = $element;
	}

	private function getElementFromAttrIndex(array & $index, string $key, $value): array
	{
		if (!isset($index[$key])) {
			throw new MissingAttrIndexException("Attribute index \"$key\" is not defined.");
		}

		return $index[$key][$value] ?? [];
	}

	public function nodeAttrChanged($node, $key, $oldValue, $newValue)
	{
		if (isset($this->nodeAttrIndex[$key])) {
			$this->updateAttrIndex($this->nodeAttrIndex, $key, $oldValue, $newValue, $node);
		}
	}

	public function edgeAttrChanged($node, $key, $oldValue, $newValue)
	{
		if (isset($this->edgeAttrIndex[$key])) {
			$this->updateAttrIndex($this->edgeAttrIndex, $key, $oldValue, $newValue, $node);
		}
	}


}

