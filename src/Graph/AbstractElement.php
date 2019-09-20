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

namespace Smalldb\StateMachine\Graph;


abstract class AbstractElement implements \ArrayAccess
{

	/**
	 * @var array
	 */
	private $attrs;


	public function __construct(array $attrs = [])
	{
		$this->attrs = $attrs;
	}


	public function offsetSet($key, $value)
	{
		return $this->setAttr($key, $value);
	}


	public function & offsetGet($key)
	{
		return $this->attrs[$key];
	}


	public function getAttr(string $key, $defaultValue = null)
	{
		return $this->attrs[$key] ?? $defaultValue;
	}


	public function offsetUnset($key)
	{
		$this->removeAttr($key);
	}


	/**
	 * @return $this
	 */
	public function setAttr(string $key, $newValue)
	{
		$oldValue = $this->attrs[$key] ?? null;
		$this->attrs[$key] = $newValue;
		$this->onAttrChanged($key, $oldValue, $newValue);
		return $this;
	}


	/**
	 * @return $this
	 */
	public function removeAttr(string $key)
	{
		$this->setAttr($key, null);
		unset($this->attrs[$key]);
		return $this;
	}


	public function offsetExists($key)
	{
		return isset($this->attrs[$key]);
	}


	public function getAttributes(): array
	{
		return $this->attrs;
	}


	abstract protected function onAttrChanged(string $key, $oldValue, $newValue);
}
