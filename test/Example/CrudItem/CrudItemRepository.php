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
use Smalldb\StateMachine\Test\Example\Database\ArrayDao;
use Smalldb\StateMachine\UnsupportedReferenceException;


/**
 * Crud item repository -- a simple array-based testing repository.
 */
class CrudItemRepository implements SmalldbRepositoryInterface
{
	private $smalldb;
	private $dao;

	private $items = [];

	public function __construct(Smalldb $smalldb, ArrayDao $dao)
	{
		$this->smalldb = $smalldb;
		$this->dao = $dao;
	}

	public function getState(ReferenceInterface $ref): string
	{
		if ($ref instanceof CrudItemRef) {
			$id = $ref->getId();
			return $id !== null && $this->dao->exists($id)
				? CrudItemMachine::EXISTS
				: CrudItemMachine::NOT_EXISTS;
		} else {
			throw new UnsupportedReferenceException('Unsupported reference: ' . get_class($ref));
		}
	}

	public function ref(...$id): CrudItemRef
	{
		return new CrudItemRef($this->smalldb, $this, $id[0]);
	}

}
