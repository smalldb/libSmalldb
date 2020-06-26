<?php declare(strict_types = 1);
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

namespace Smalldb\Graph;

class NestedGraph extends AbstractElement
{

	/**
	 * Nodes
	 *
	 * @var Node[]
	 */
	private array $nodes = [];

	/**
	 * @var Edge[]
	 */
	private array $edges = [];

	/**
	 * Node owning this graph if the graph is nested.
	 */
	private ?Node $parentNode = null;

	/**
	 * Root graph
	 */
	protected ?Graph $rootGraph = null;

	/**
	 * Node index
	 */
	protected ElementAttrIndex $nodeAttrIndex;

	/**
	 * Edge index
	 */
	protected ElementAttrIndex $edgeAttrIndex;


	/**
	 * Constructor.
	 */
	protected function __construct(Node $parentNode = null, array $attrs = [])
	{
		parent::__construct($attrs);
		if ($parentNode) {
			$this->parentNode = $parentNode;
			$this->rootGraph = $this->parentNode->getGraph()->getRootGraph();
			$this->nodeAttrIndex = $this->rootGraph->nodeAttrIndex;
			$this->edgeAttrIndex = $this->rootGraph->edgeAttrIndex;
		}
	}


	public function getRootGraph(): Graph
	{
		return $this->rootGraph;
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
	 * Get all edges in the graph
	 *
	 * @return Edge[]
	 */
	public function getEdges(): array
	{
		return $this->edges;
	}


	/**
	 * Create node and add it to the graph.
	 *
	 * @param string $id
	 * @param array $attrs
	 * @return Node
	 */
	public function createNode(string $id, array $attrs = []): Node
	{
		return new Node($this, $id, $attrs);
	}


	/**
	 * Add existing node to the graph.
	 *
	 * @param Node $node
	 * @return NestedGraph
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
		$this->nodeAttrIndex->insertElement($node);

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
	 * Returns true if a given node exists within the graph.
	 */
	public function hasNode(string $id): bool
	{
		return isset($this->nodes[$id]);
	}


	/**
	 * Remove node and connected edges from the graph.
	 *
	 * @param Node $node
	 * @return NestedGraph
	 */
	public function removeNode(Node $node): self
	{
		$id = $node->getId();
		if (isset($this->nodes[$id])) {
			foreach ($node->getConnectedEdges() as $e) {
				$e->remove();
			}
			unset($this->nodes[$id]);
			$this->nodeAttrIndex->removeElement($node);
			return $this;
		} else {
			throw new MissingNodeException(sprintf("Node \"%s\" not found in the graph.", $id));
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
	public function createEdge(?string $id, Node $start, Node $end, array $attrs = []): Edge
	{
		if ($id === null) {
			$id = '_' . count($this->getRootGraph()->getAllEdges());
		}

		return new Edge($this, $id, $start, $end, $attrs);
	}


	/**
	 * Add existing edge to the graph.
	 *
	 * @param Edge $edge
	 * @return NestedGraph
	 */
	public function addEdge(Edge $edge): self
	{
		$id = $edge->getId();
		$start = $edge->getStart();
		$end = $edge->getEnd();

		if (isset($this->rootGraph->edges[$id])) {
			throw new DuplicateEdgeException(sprintf("Edge \"%s\" already present in the graph.", $id));
		}

		// Connect the edge to the nodes
		$start->connectEdge($edge);
		if ($start !== $end) {
			$end->connectEdge($edge);
		}

		// Add element
		$this->edges[$id] = $edge;

		// Update attr indexes
		$this->edgeAttrIndex->insertElement($edge);

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
	 * Returns true if a given edge exists within the graph.
	 */
	public function hasEdge(string $id): bool
	{
		return isset($this->edges[$id]);
	}


	/**
	 * Remove edge from the graph and disconnect it from nodes.
	 *
	 * @param Edge $edge
	 * @return NestedGraph
	 */
	public function removeEdge(Edge $edge): self
	{
		$id = $edge->getId();
		if (isset($this->edges[$id])) {
			if ($this->edges[$id] !== $edge) {
				throw new MissingEdgeException(sprintf("Edge \"%s\" found in the graph is not the expected edge.", $id));  // @codeCoverageIgnore
			}
			$this->edges[$id]->disconnectNodes();
			unset($this->edges[$id]);
			$this->edgeAttrIndex->removeElement($edge);
			return $this;
		} else {
			throw new MissingEdgeException(sprintf("Edge \"%s\" not found in the graph.", $id));
		}
	}


	public function getParentNode(): ?Node
	{
		return $this->parentNode;
	}


	/**
	 * @internal
	 */
	public function nodeAttrChanged($node, $key, $oldValue, $newValue)
	{
		if ($this->nodeAttrIndex->hasAttrIndex($key)) {
			$this->nodeAttrIndex->update($key, $oldValue, $newValue, $node);
		}
	}


	/**
	 * @internal
	 */
	public function edgeAttrChanged($node, $key, $oldValue, $newValue)
	{
		if ($this->edgeAttrIndex->hasAttrIndex($key)) {
			$this->edgeAttrIndex->update($key, $oldValue, $newValue, $node);
		}
	}

	/**
	 * Handle change of an attribute.
	 *
	 * @codeCoverageIgnore
	 */
	protected function onAttrChanged(string $key, $oldValue, $newValue)
	{
		// No-op.
	}

}

