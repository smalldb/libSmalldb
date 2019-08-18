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

namespace Smalldb\StateMachine\Test\Example\CrudItem;

use InvalidArgumentException;
use Smalldb\StateMachine\Provider\SmalldbProviderInterface;
use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\SmalldbRepositoryInterface;
use Smalldb\StateMachine\Test\Database\ArrayDaoTables;
use Smalldb\StateMachine\UnsupportedReferenceException;


abstract class AbstractCrudRepository implements SmalldbRepositoryInterface
{
	protected const MACHINE_TYPE = 'crud-item';
	protected const REFERENCE_CLASS = CrudItem::class;

	/** @var string */
	private $table;

	/** @var Smalldb */
	private $smalldb;

	/** @var string */
	private $refClass;

	/** @var ArrayDaoTables */
	private $dao;

	/** @var SmalldbProviderInterface */
	private $machineProvider;


	public function __construct(Smalldb $smalldb, ArrayDaoTables $dao)
	{
		$this->smalldb = $smalldb;

		$this->table = get_class($this);
		$this->dao = $dao;

		// In a real-world application, we would create the table in a database migration.
		$this->dao->createTable($this->table);
	}


	protected function supports(ReferenceInterface $ref): bool
	{
		$className = static::REFERENCE_CLASS;
		return $ref instanceof $className;
	}


	public function getTableName(): string
	{
		return $this->table;
	}


	public function getState(ReferenceInterface $ref): string
	{
		if (!$this->supports($ref)) {
			throw new UnsupportedReferenceException('Unsupported reference: ' . get_class($ref));
		}

		$id = (int) $ref->getId();
		return $id !== null && $this->dao->table($this->table)->exists($id)
			? CrudItem::EXISTS
			: CrudItem::NOT_EXISTS;
	}


	public function getData(ReferenceInterface $ref, & $state)
	{
		if (!$this->supports($ref)) {
			throw new UnsupportedReferenceException('Unsupported reference: ' . get_class($ref));
		}

		$id = (int) $ref->getId();
		if ($id !== null) {
			$data = $this->dao->table($this->table)->read($id);
			$state = CrudItem::EXISTS;
			return $data;
		} else {
			$state = CrudItem::NOT_EXISTS;
			return null;
		}
	}


	private function createPreheatedReference($item): ReferenceInterface
	{
		if (!$this->machineProvider) {
			$this->machineProvider = $this->smalldb->getMachineProvider(static::REFERENCE_CLASS);
			$this->refClass = $this->machineProvider->getReferenceClass();
		}

		/** @var ReferenceInterface $ref */
		$ref = new $this->refClass($item);
		$ref->smalldbConnect($this->smalldb, $this->machineProvider);
		return $ref;
	}


	private function createPreheatedReferenceCollection(array $items): array
	{
		$result = [];
		foreach ($items as $k => $item) {
			$result[$k] = $this->createPreheatedReference($item);
		}
		return $result;
	}


	public function ref($id): ReferenceInterface
	{
		if (!$this->machineProvider) {
			$this->machineProvider = $this->smalldb->getMachineProvider(static::REFERENCE_CLASS);
			$this->refClass = $this->machineProvider->getReferenceClass();
		}

		/** @var ReferenceInterface $ref */
		$ref = new $this->refClass($id);
		$ref->smalldbConnect($this->smalldb, $this->machineProvider);
		return $ref;
	}


	public function findOneBy(array $properties): ReferenceInterface
	{
		// Find the first matching item. It does not have to be fast;
		// we have only a few items anyway.
		$items = $this->dao->table($this->table)->getFilteredSlice(function($item) use ($properties) {
			foreach ($properties as $key => $expectedValue) {
				if ($item[$key] !== $expectedValue) {
					return false;
				}
			}
			return true;
		}, 0, 1);

		if (empty($items)) {
			throw new InvalidArgumentException("Item not found: "
				. json_encode($properties, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		} else {
			$item = reset($items);
			return $this->createPreheatedReference($item);
		}
	}


	/**
	 * @return ReferenceInterface[]
	 */
	public function findLatest(?int $page, ?ReferenceInterface $tag): array
	{
		$table = $this->dao->table($this->table);
		$pageSize = 10;
		$offset = $page * $pageSize;

		if ($tag) {
			$items = $table->getFilteredSlice(function ($item) use ($tag) {
				return in_array($tag->getId(), $item['tags']);
			}, $offset, $pageSize);
		} else {
			$items = $table->getSlice($offset, $pageSize);
		}

		return $this->createPreheatedReferenceCollection($items);
	}

}
