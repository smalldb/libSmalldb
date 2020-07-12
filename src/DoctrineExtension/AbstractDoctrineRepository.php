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

namespace Smalldb\StateMachine\DoctrineExtension;

use Doctrine\ORM\EntityRepository;
use Smalldb\StateMachine\AbstractSmalldbRepository;
use Smalldb\StateMachine\DoctrineExtension\ReferenceDataSource\DataSource;
use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\Smalldb;


class AbstractDoctrineRepository extends AbstractSmalldbRepository
{
	private ?DataSource $dataSource = null;
	protected EntityRepository $repository;


	public function __construct(Smalldb $smalldb, EntityRepository $repository)
	{
		parent::__construct($smalldb);
		$this->repository = $repository;
	}


	protected function getDataSource(): DataSource
	{
		return $this->dataSource ?? ($this->dataSource = $this->createDataSource());
	}


	protected function createDataSource(): DataSource
	{
		return new DataSource($this->smalldb, $this->getMachineProvider(), $this->repository);
	}


	/**
	 * Create a reference to a state machine identified by $id.
	 *
	 * @return ReferenceInterface
	 */
	public function ref($id)
	{
		return $this->getDataSource()->ref($id);
	}

}
