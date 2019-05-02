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

use Smalldb\StateMachine\Reference;
use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\SmalldbRepositoryInterface;
use Smalldb\StateMachine\Test\Database\ArrayDaoTables;
use Smalldb\StateMachine\UnsupportedReferenceException;


/**
 * Crud item repository -- a simple array-based testing repository.
 */
class CrudItemRepository implements SmalldbRepositoryInterface
{
	/** @var string */
	private $table;

	/** @var Smalldb */
	private $smalldb;

	/** @var ArrayDaoTables */
	private $dao;


	public function __construct(Smalldb $smalldb, ArrayDaoTables $dao)
	{
		$this->table = get_class($this);
		$this->smalldb = $smalldb;
		$this->dao = $dao;

		// In a real-world application, we would create the table in a database migration.
		$this->dao->createTable($this->table);
	}


	public function getTableName(): string
	{
		return $this->table;
	}


	public function getState(ReferenceInterface $ref): string
	{
		if ($ref instanceof CrudItemRef) {
			$id = (int) $ref->getId();
			return $id !== null && $this->dao->table($this->table)->exists($id)
				? CrudItemMachine::EXISTS
				: CrudItemMachine::NOT_EXISTS;
		} else {
			throw new UnsupportedReferenceException('Unsupported reference: ' . get_class($ref));
		}
	}

	public function ref(...$id): CrudItemRef
	{
		$ref = $this->smalldb->ref('crud-item', ...$id);
		if ($ref instanceof CrudItemRef) {
			return $ref;
		} else {
			throw new UnsupportedReferenceException('The new reference should be instance of '
				. CrudItemRef::class . ', but it is ' . get_class($ref) . ' instead.');
		}
	}

}
