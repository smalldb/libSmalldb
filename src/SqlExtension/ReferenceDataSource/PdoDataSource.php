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
use Smalldb\StateMachine\ReferenceDataSource\ReferenceDataSourceInterface;


/**
 * Class PdoDataSource
 *
 * @deprecated
 */
class PdoDataSource implements ReferenceDataSourceInterface
{
	private PDOStatement $stateSelectStmt;
	private PDOStatement $loadDataStmt;

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
	 * Load data for the state machine and set the state
	 */
	public function loadData($id)
	{
		$stmt = $this->loadDataStmt;

		// TODO: Support composed primary keys.
		$stmt->execute(['id' => $id]);
		if ($this->onQueryCallback) {
			($this->onQueryCallback)($stmt);
		}

		$data = $stmt->fetch(PDO::FETCH_ASSOC);
		$stmt->closeCursor();

		return $data ?: null;
	}


	/**
	 * Invalidate cached data
	 */
	public function invalidateCache($id = null)
	{
		// No caching nor preloading.
	}

}
