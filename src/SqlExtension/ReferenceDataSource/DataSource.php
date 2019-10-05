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

namespace Smalldb\StateMachine\SqlExtension\ReferenceDataSource;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\FetchMode;
use Smalldb\StateMachine\InvalidArgumentException;
use Smalldb\StateMachine\Provider\SmalldbProviderInterface;
use Smalldb\StateMachine\ReferenceDataSource\LogicException;
use Smalldb\StateMachine\ReferenceDataSource\NotExistsException;
use Smalldb\StateMachine\ReferenceDataSource\ReferenceDataSourceInterface;
use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\Smalldb;


class DataSource implements ReferenceDataSourceInterface
{

	/** @var Smalldb */
	protected $smalldb;

	/** @var SmalldbProviderInterface */
	protected $machineProvider;

	/** @var string */
	protected $refClass;

	/** @var Connection */
	private $db;

	/** @var callable|null */
	private $onQueryCallback = null;


	public function __construct(?DataSource $originalDataSource, Smalldb $smalldb = null, SmalldbProviderInterface $machineProvider = null, Connection $db = null)
	{
		if ($originalDataSource === null) {
			if ($smalldb && $machineProvider && $db) {
				$this->smalldb = $smalldb;
				$this->machineProvider = $machineProvider;
				$this->refClass = $this->machineProvider->getReferenceClass();
				$this->db = $db;
			} else {
				throw new \InvalidArgumentException("Missing argument(s).");
			}
		} else {
			$this->smalldb = $originalDataSource->smalldb;
			$this->machineProvider = $originalDataSource->machineProvider;
			$this->refClass = $originalDataSource->refClass;
			$this->db = $originalDataSource->db;
		}
	}


	public function setOnQueryCallback(?callable $onQueryCallback): void
	{
		$this->onQueryCallback = $onQueryCallback;
	}


	public function ref($id): ReferenceInterface
	{
		return new $this->refClass($this->smalldb, $this->machineProvider, $this, $id);
	}


	public function createQueryBuilder(string $tableAlias = 'this'): ReferenceQueryBuilder
	{
		return new ReferenceQueryBuilder($this->smalldb, $this->machineProvider, $this, $tableAlias);
	}


	/**
	 * Load data for the state machine and set the state
	 */
	public function loadData($id): ?array
	{
		if ($id === null) {
			return null;
		}

		$q = $this->createQueryBuilder()
			->addSelectFromStatements()
			->andWhereId($id);

		$stmt = $q->execute();

		if ($this->onQueryCallback) {
			($this->onQueryCallback)($q);
		}

		if ($stmt instanceof Statement) {
			$data = $stmt->fetch(FetchMode::ASSOCIATIVE);
		} else {
			throw new LogicException("Load data select does not return a result set.");
		}

		return $data ?: null;
	}


	/**
	 * Invalidate cached data
	 */
	public function invalidateCache($id = null)
	{
		// No caching nor preloading.
	}


	/**
	 * @return Connection
	 */
	public function getConnection(): Connection
	{
		return $this->db;
	}

}
