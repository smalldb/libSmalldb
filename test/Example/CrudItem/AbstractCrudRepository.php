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
use Smalldb\StateMachine\Test\Database\DaoDataSource;


abstract class AbstractCrudRepository implements SmalldbRepositoryInterface
{
	protected const MACHINE_TYPE = 'crud-item';
	protected const REFERENCE_CLASS = CrudItem::class;

	private string $table;
	private Smalldb $smalldb;
	private ?string $refClass = null;
	private ArrayDaoTables $dao;
	private ?SmalldbProviderInterface $machineProvider = null;
	private DaoDataSource $dataSource;


	public function __construct(Smalldb $smalldb, ArrayDaoTables $dao)
	{
		$this->smalldb = $smalldb;

		$this->table = get_class($this);
		$this->dao = $dao;

		// In a real-world application, we would create the table in a database migration.
		$this->dao->createTable($this->table);

		$this->dataSource = new DaoDataSource($this->dao, $this->getTableName());
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


	private function createPreheatedReference($item): ReferenceInterface
	{
		if (!$this->machineProvider) {
			$this->machineProvider = $this->smalldb->getMachineProvider(static::REFERENCE_CLASS);
			$this->refClass = $this->machineProvider->getReferenceClass();
		}

		/** @var ReferenceInterface $ref */
		$ref = new $this->refClass($this->smalldb, $this->machineProvider, $this->dataSource, $item);
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
		$ref = new $this->refClass($this->smalldb, $this->machineProvider, $this->dataSource, $id);
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

}
