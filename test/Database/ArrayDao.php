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

namespace Smalldb\StateMachine\Test\Database;


/**
 * A simple array-based data access object for use in tests. In a real
 * application, this class would connect to a database, or ORM would
 * be used instead.
 */
class ArrayDao
{
	private array $items = [];

	public function read(int $id)
	{
		if (isset($this->items[$id])) {
			return $this->items[$id];
		} else {
			throw new \InvalidArgumentException("Item not found: $id");
		}
	}

	public function exists(int $id): bool
	{
		return isset($this->items[$id]);
	}

	public function create($data): int
	{
		$id = count($this->items) + 1;
		$this->items[$id] = $data;
		return $id;
	}

	public function update(int $id, $newData): void
	{
		if (isset($this->items[$id])) {
			if (is_array($newData) && is_array($this->items[$id])) {
				$this->items[$id] = array_merge($this->items[$id], $newData);
			} else {
				$this->items[$id] = $newData;
			}
		} else {
			throw new \InvalidArgumentException("Item not found: $id");
		}
	}

	public function delete(int $id): void
	{
		if (isset($this->items[$id])) {
			unset($this->items[$id]);
		} else {
			throw new \InvalidArgumentException("Item not found: $id");
		}
	}


	public function getCount(): int
	{
		return count($this->items);
	}


	public function getSlice(int $offset, ?int $length = null): array
	{
		if ($offset >= count($this->items)) {
			throw new \RangeException('Offset out of bounds.');
		}

		return array_slice($this->items, $offset, $length, true);
	}


	public function getFilteredSlice(callable $filter, int $offset, ?int $length = null): array
	{
		$result = [];
		$resultLength = 0;
		$position = 0;

		if ($offset >= count($this->items)) {
			throw new \RangeException('Offset out of bounds.');
		}

		foreach ($this->items as $id => $item) {
			if ($filter($item)) {
				if (++$position <= $offset) {
					continue;
				}

				$result[$id] = $item;
				$resultLength++;
				if ($length !== null && $resultLength >= $length) {
					return $result;
				}
			}
		}

		if ($position <= $offset) {
			throw new \RangeException('Offset out of bounds.');
		}

		return $result;
	}

	public function getIterator(): \Iterator
	{
		return new \ArrayIterator($this->items);
	}

}
