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

namespace Smalldb\StateMachine\ReferenceDataSource\DoctrineDBAL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Smalldb\StateMachine\Provider\SmalldbProviderInterface;
use Smalldb\StateMachine\ReferenceDataSource\NotExistsException;
use Smalldb\StateMachine\ReferenceDataSource\ReferenceDataSourceInterface;
use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\Smalldb;


class DataSource implements ReferenceDataSourceInterface
{

	/** @var Smalldb */
	protected $smalldb;

	/** @var SmalldbProviderInterface|null */
	protected $machineProvider = null;

	/** @var string */
	protected $refClass = null;

	/** @var Connection */
	private $db;

	/** @var callable */
	private $onQueryCallback = null;


	public function __construct(Smalldb $smalldb, SmalldbProviderInterface $machineProvider, Connection $db)
	{
		$this->smalldb = $smalldb;
		$this->machineProvider = $machineProvider;
		$this->refClass = $this->machineProvider->getReferenceClass();
		$this->db = $db;
	}


	public function setOnQueryCallback(?callable $onQueryCallback): void
	{
		$this->onQueryCallback = $onQueryCallback;
	}


	public function ref($id): ReferenceInterface
	{
		return new $this->refClass($this->smalldb, $this->machineProvider, $this, $id);
	}


	public function createQueryBuilder(): ReferenceQueryBuilder
	{
		return new ReferenceQueryBuilder($this->db, $this->smalldb, $this->machineProvider, $this);
	}


	/**
	 * Return the state of the refered state machine.
	 */
	public function getState($id): string
	{
		$q = $this->createQueryBuilder()
			->addSelectFromStatements(true)
			->andWhereId($id);

		if ($this->onQueryCallback) {
			($this->onQueryCallback)($q);
		}

		$stmt = $q->execute();
		$state = $stmt->fetchColumn();
		return $state !== false ? (string) $state : '';
	}


	/**
	 * Load data for the state machine and set the state
	 */
	public function loadData($id, string &$state = null)
	{
		$q = $this->createQueryBuilder()
			->addSelectFromStatements()
			->andWhereId($id);

		if ($this->onQueryCallback) {
			($this->onQueryCallback)($q);
		}

		$stmt = $q->execute();
		$data = $stmt->fetch(FetchMode::ASSOCIATIVE);

		$state = $data['state'] ?? null;
		if (empty($data) || $state === '') {
			$state = '';
			throw new NotExistsException('Cannot load data in the Not Exists state.');
		}
		return $data;
	}


	/**
	 * Invalidate cached data
	 */
	public function invalidateCache($id = null)
	{
		// No caching nor preloading.
	}

}
