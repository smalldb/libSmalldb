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

namespace Smalldb\StateMachine\SqlExtension;

use Doctrine\DBAL\Connection;
use Smalldb\StateMachine\AbstractSmalldbRepository;
use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\SmalldbRepositoryInterface;
use Smalldb\StateMachine\SqlExtension\ReferenceDataSource\DataSource;


/**
 * Class AbstractSqlRepository
 *
 * This class implements a simple repository of a single entity class.
 */
abstract class AbstractSqlRepository extends AbstractSmalldbRepository implements SmalldbRepositoryInterface
{
	protected Connection $db;
	private ?DataSource $dataSource = null;


	public function __construct(Smalldb $smalldb, Connection $db)
	{
		parent::__construct($smalldb);
		$this->db = $db;
	}


	protected function getDataSource(): DataSource
	{
		return $this->dataSource ?? ($this->dataSource = $this->createDataSource());
	}


	protected function createDataSource(): DataSource
	{
		return new DataSource(null, $this->smalldb, $this->getMachineProvider(), $this->db);
	}


	/**
	 * Create a reference to a state machine identified by $id.
	 * Override this method to use a proper return type hint.
	 *
	 * @return ReferenceInterface
	 */
	public function ref($id)
	{
		return $this->getDataSource()->ref($id);
	}

}
