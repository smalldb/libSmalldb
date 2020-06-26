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


class Node extends AbstractGraphElement
{

	/**
	 * List of edges connected to this node.
	 *
	 * The edge may in this node start, or end, or both (or more in case
	 * of hyper-edges). However, the edge is present in the list only once.
	 *
	 * @var Edge[]
	 */
	private $connectedEdges = [];

	/**
	 * Nested graph.
	 *
	 * @var NestedGraph|null
	 */
	private $nestedGraph = null;


	public function __construct(NestedGraph $graph, string $id, array $attrs)
	{
		parent::__construct($graph, $id, $attrs);

		$this->getGraph()->addNode($this);
	}


	public function remove()
	{
		$this->getGraph()->removeNode($this);
	}


	public function connectEdge(Edge $edge): self
	{
		$id = $edge->getId();
		if (isset($this->connectedEdges[$id])) {
			throw new DuplicateEdgeException(sprintf("Edge \"%s\" already connected to node \"%s\".", $id, $this->getId()));  //@codeCoverageIgnore
		} else {
			$this->connectedEdges[$id] = $edge;
			return $this;
		}
	}


	public function disconnectEdge(Edge $edge): self
	{
		$id = $edge->getId();
		if (isset($this->connectedEdges[$id])) {
			unset($this->connectedEdges[$id]);
			return $this;
		} else {
			throw new MissingEdgeException(sprintf("Edge \"%s\" is not connected to node \"%s\".", $id, $this->getId()));  //@codeCoverageIgnore
		}
	}


	/**
	 * Get list of edges connected to this node.
	 *
	 * An edge may be connected multiple times to the node, but it will
	 * be present in this list only once.
	 *
	 * @return Edge[]
	 */
	public function getConnectedEdges(): array
	{
		return $this->connectedEdges;
	}


	/**
	 * Get nested graph of this node. The graph is created if the node does not have any.
	 *
	 * @return NestedGraph
	 */
	public function getNestedGraph(): NestedGraph
	{
		return $this->nestedGraph !== null
			? $this->nestedGraph
			: ($this->nestedGraph = $this->getRootGraph()->createNestedGraph($this));
	}


	/**
	 * Does the node have a nested graph?
	 */
	public function hasNestedGraph(): bool
	{
		return $this->nestedGraph !== null;
	}


	/**
	 * Handle change of an attribute.
	 */
	protected function onAttrChanged(string $key, $oldValue, $newValue)
	{
		$this->getGraph()->nodeAttrChanged($this, $key, $oldValue, $newValue);
	}

}
