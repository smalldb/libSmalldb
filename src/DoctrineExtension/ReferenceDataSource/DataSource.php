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

namespace Smalldb\StateMachine\DoctrineExtension\ReferenceDataSource;

use Doctrine\ORM\EntityRepository;
use Smalldb\StateMachine\Provider\SmalldbProviderInterface;
use Smalldb\StateMachine\ReferenceDataSource\ReferenceDataSourceInterface;
use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\Smalldb;


class DataSource implements ReferenceDataSourceInterface
{
	private Smalldb $smalldb;
	private SmalldbProviderInterface $machineProvider;
	private EntityRepository $repository;
	private string $refClass;


	public function __construct(Smalldb $smalldb, SmalldbProviderInterface $machineProvider, EntityRepository $repository)
	{
		$this->smalldb = $smalldb;
		$this->machineProvider = $machineProvider;
		$this->repository = $repository;

		$this->refClass = $this->machineProvider->getReferenceClass();
	}


	/**
	 * Load data for the state machine and set the state
	 */
	public function loadData($id): ?object
	{
		if ($id === null) {
			return null;
		}
		return $this->repository->find($id);
	}


	public function ref($id): ReferenceInterface
	{
		return new $this->refClass($this->smalldb, $this->machineProvider, $this, $id);
	}


	/**
	 * Invalidate cached data
	 */
	public function invalidateCache($id = null)
	{
		// No-op.
	}

}
