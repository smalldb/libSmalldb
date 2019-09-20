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


namespace Smalldb\StateMachine\Graph;


class Edge extends AbstractGraphElement
{
	/**
	 * @var Node
	 */
	private $start;

	/**
	 * @var Node
	 */
	private $end;


	public function __construct(NestedGraph $graph, string $id, Node $start, Node $end, array $attrs)
	{
		parent::__construct($graph, $id, $attrs);
		$this->start = $start;
		$this->end = $end;

		$this->getGraph()->addEdge($this);
	}


	public function remove()
	{
		$this->getGraph()->removeEdge($this);
	}


	public function getStart(): Node
	{
		return $this->start;
	}


	public function setStart(Node $newStart): self
	{
		if ($newStart !== $this->end) {
			$this->start->disconnectEdge($this);
		}

		$this->start = $newStart;

		if ($this->start !== $this->end) {
			$this->start->connectEdge($this);
		}
		return $this;
	}


	public function getEnd(): Node
	{
		return $this->end;
	}


	public function setEnd(Node $newEnd): self
	{
		if ($this->start !== $newEnd) {
			$this->end->disconnectEdge($this);
		}

		$this->end = $newEnd;

		if ($this->start !== $this->end) {
			$this->end->connectEdge($this);
		}
		return $this;
	}


	public function disconnectNodes(): self
	{
		$this->start->disconnectEdge($this);
		if ($this->start !== $this->end) {
			$this->end->disconnectEdge($this);
		}
		return $this;
	}


	/**
	 * Handle change of an attribute.
	 */
	protected function onAttrChanged(string $key, $oldValue, $newValue)
	{
		$this->getGraph()->edgeAttrChanged($this, $key, $oldValue, $newValue);
	}
}
