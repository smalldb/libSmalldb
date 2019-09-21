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

use PDO;
use PDOStatement;
use Smalldb\StateMachine\ReferenceDataSource\NotExistsException;
use Smalldb\StateMachine\ReferenceDataSource\ReferenceDataSourceInterface;


/**
 * Class PdoDataSource
 *
 * @deprecated
 */
class PdoDataSource implements ReferenceDataSourceInterface
{

	/** @var PDOStatement */
	private $stateSelectStmt;

	/** @var PDOStatement */
	private $loadDataStmt;

	/** @var callable|null */
	private $onQueryCallback = null;


	public function __construct()
	{
	}


	public function setStateSelectPreparedStatement(PDOStatement $stateSelectStmt): void
	{
		$this->stateSelectStmt = $stateSelectStmt;
	}


	public function setLoadDataPreparedStatement(PDOStatement $loadDataStmt): void
	{
		$this->loadDataStmt = $loadDataStmt;
	}


	public function setOnQueryCallback(?callable $onQueryCallback): void
	{
		$this->onQueryCallback = $onQueryCallback;
	}


	/**
	 * Return the state of the refered state machine.
	 */
	public function getState($id): string
	{
		$stmt = $this->stateSelectStmt;

		$stmt->execute(['id' => $id]);
		if ($this->onQueryCallback) {
			($this->onQueryCallback)($stmt);
		}

		$state = $stmt->fetchColumn(0);
		$stmt->closeCursor();
		return $state === false || $state === null ? '' : $state;
	}


	/**
	 * Load data for the state machine and set the state
	 */
	public function loadData($id, string &$state = null)
	{
		$stmt = $this->loadDataStmt;

		$stmt->execute(['id' => $id]);
		if ($this->onQueryCallback) {
			($this->onQueryCallback)($stmt);
		}

		$data = $stmt->fetch(PDO::FETCH_ASSOC);
		$stmt->closeCursor();

		if ($data === false) {
			$state = '';
			throw new NotExistsException('Cannot load data in the Not Exists state.');
		}

		$state = $data['state'] ?? null;
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
