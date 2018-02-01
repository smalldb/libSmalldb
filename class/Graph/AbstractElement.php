<?php
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


abstract class AbstractElement implements \ArrayAccess
{
	/**
	 * @var string
	 */
	private $id;

	/**
	 * @var Graph
	 */
	private $graph;

	/**
	 * @var array
	 */
	private $attrs;


	public function __construct(Graph $graph, string $id, array $attrs)
	{
		$this->graph = $graph;
		$this->id = $id;
		$this->attrs = $attrs;
	}


	public function getId(): string
	{
		return $this->id;
	}


	public function getGraph(): Graph
	{
		return $this->graph;
	}


	public function setAttr(string $key, $newValue): self
	{
		$oldValue = $this->attrs[$key] ?? null;
		$this->attrs[$key] = $newValue;
		$this->onAttrChanged($key, $oldValue, $newValue);
		return $this;
	}


	public function getAttr(string $key, $defaultValue = null)
	{
		return $this->attrs[$key] ?? $defaultValue;
	}


	public function removeAttr(string $key): self
	{
		$this->setAttr($key, null);
		unset($this->attrs[$key]);
		return $this;
	}


	public function getAttributes(): array
	{
		return $this->attrs;
	}


	abstract protected function onAttrChanged(string $key, $oldValue, $newValue);


	public function offsetExists($key)
	{
		return isset($this->attrs[$key]);
	}


	public function & offsetGet($key)
	{
		return $this->attrs[$key];
	}


	public function offsetSet($key, $value)
	{
		return $this->setAttr($key, $value);
	}


	public function offsetUnset($key)
	{
		$this->removeAttr($key);
	}

}
