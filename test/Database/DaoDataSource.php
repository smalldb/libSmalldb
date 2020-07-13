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

use Smalldb\StateMachine\ReferenceDataSource\ReferenceDataSourceInterface;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItem;


class DaoDataSource implements ReferenceDataSourceInterface
{
	private ArrayDaoTables $dao;
	private string $table;


	public function __construct(ArrayDaoTables $dao, string $table)
	{
		$this->dao = $dao;
		$this->table = $table;
	}


	public function getState($id): string
	{
		return $id !== null && $this->dao->table($this->table)->exists($id)
			? CrudItem::EXISTS
			: CrudItem::NOT_EXISTS;
	}


	public function loadData($id)
	{
		$daoTable = $this->dao->table($this->table);
		if ($id === null || !$daoTable->exists($id)) {
			return null;
		} else {
			return $daoTable->read($id);
		}
	}


	/**
	 * Invalidate cached data
	 */
	public function invalidateCache($id = null)
	{
		// No cache.
	}

}
