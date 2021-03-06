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


class ElementAttrIndex
{
	/**
	 * @var AbstractGraphElement[][][]
	 */
	private array $index = [];

	/**
	 * @var AbstractGraphElement[]
	 */
	private array $elements = [];

	private string $elementClassName;


	/**
	 * ElementAttrIndex constructor.
	 */
	public function __construct($elementClassName = AbstractGraphElement::class)
	{
		$this->elementClassName = $elementClassName;
	}


	public function getElementById(string $id): AbstractGraphElement
	{
		if (!isset($this->elements[$id])) {
			throw new MissingElementException("Element \"$id\" is not indexed.");
		}

		return $this->elements[$id];
	}


	/**
	 * @param string $key Attribute to index
	 */
	public function createAttrIndex(string $key)
	{
		if (isset($this->index[$key])) {
			throw new DuplicateAttrIndexException("Attribute index \"$key\" already exists.");
		}
		$this->rebuildAttrIndex($key);
	}


	/**
	 * Returns true if the attribute is indexed.
	 */
	public function hasAttrIndex(string $key): bool
	{
		return isset($this->index[$key]);
	}


	/**
	 * Rebuild the entire index from provided elements.
	 */
	private function rebuildAttrIndex(string $key)
	{
		$this->index[$key] = [];

		foreach ($this->elements as $id => $element) {
			if ($element instanceof $this->elementClassName) {
				$value = $element->getAttr($key);
				$this->index[$key][$value][$id] = $element;
			} else {
				throw new \InvalidArgumentException("Indexed element must be instance of " . $this->elementClassName);  // @codeCoverageIgnore
			}
		}
	}


	/**
	 * Insert element into index (all keys has changed).
	 */
	public function insertElement(AbstractGraphElement $element)
	{
		if (!($element instanceof $this->elementClassName)) {
			throw new \InvalidArgumentException("Indexed element must be instance of " . $this->elementClassName);  // @codeCoverageIgnore
		}

		$id = $element->getId();

		if (isset($this->elements[$id])) {
			throw new DuplicateElementException("Element \"$id\" already indexed.");  // @codeCoverageIgnore
		}

		$this->elements[$id] = $element;

		$indexedAttrs = array_intersect_key($element->getAttributes(), $this->index);
		foreach ($indexedAttrs as $key => $newValue) {
			$this->index[$key][$newValue][$id] = $element;
		}
	}


	/**
	 * Remove element from index
	 */
	public function removeElement(AbstractGraphElement $element)
	{
		$id = $element->getId();

		if (!isset($this->elements[$id])) {
			throw new MissingElementException("Element \"$id\" is not indexed.");  // @codeCoverageIgnore
		}

		unset($this->elements[$id]);

		$indexedAttrs = array_intersect_key($element->getAttributes(), $this->index);
		foreach ($indexedAttrs as $key => $oldValue) {
			unset($this->index[$key][$oldValue][$id]);
		}
	}


	/**
	 * Update indices to match the changed attribute of the element
	 */
	public function update(string $key, $oldValue, $newValue, AbstractGraphElement $element)
	{
		if (!isset($this->index[$key])) {
			throw new MissingAttrIndexException("Attribute index \"$key\" is not defined.");  // @codeCoverageIgnore
		}

		$id = $element->getId();

		if (isset($this->index[$key][$oldValue][$id])) {
			unset($this->index[$key][$oldValue][$id]);
		} else {
			throw new MissingElementException("Old value of \"$id\" is not indexed. Updating an uninitialized indexed attribute?");  // @codeCoverageIgnore
		}

		$this->index[$key][$newValue][$id] = $element;
	}


	/**
	 * Get all elements.
	 *
	 * @return AbstractGraphElement[]
	 */
	public function getAllElements(): array
	{
		return $this->elements;
	}


	/**
	 * Get elements by the value of the attribute
	 */
	public function getElements(string $key, $value): array
	{
		if (!isset($this->index[$key])) {
			throw new MissingAttrIndexException("Attribute index \"$key\" is not defined.");
		}

		return $this->index[$key][$value] ?? [];
	}
}
