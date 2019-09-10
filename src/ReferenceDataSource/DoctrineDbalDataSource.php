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

namespace Smalldb\StateMachine\ReferenceDataSource;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\Query\QueryBuilder;
use Smalldb\StateMachine\Provider\SmalldbProviderInterface;
use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\SqlExtension\SqlCalculatedPropertyExtension;
use Smalldb\StateMachine\SqlExtension\SqlPropertyExtension;
use Smalldb\StateMachine\SqlExtension\SqlTableExtension;


class DoctrineDbalDataSource implements ReferenceDataSourceInterface
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


	public function __construct(Smalldb $smalldb, string $machineType, Connection $db)
	{
		$this->smalldb = $smalldb;
		$this->db = $db;

		$this->machineProvider = $this->smalldb->getMachineProvider($machineType);
		$this->refClass = $this->machineProvider->getReferenceClass();
	}


	public function setOnQueryCallback(?callable $onQueryCallback): void
	{
		$this->onQueryCallback = $onQueryCallback;
	}


	public function ref($id): ReferenceInterface
	{
		return new $this->refClass($this->smalldb, $this->machineProvider, $this, $id);
	}



	public function createQueryBuilder(bool $stateOnly = false, string $tableAlias = 'this'): QueryBuilder
	{
		$q = new QueryBuilder($this->db);

		$definition = $this->machineProvider->getDefinition();
		if ($definition->hasExtension(SqlTableExtension::class)) {
			/** @var SqlTableExtension $ext */
			$ext = $definition->getExtension(SqlTableExtension::class);
			$table = $ext->getSqlTable();
			$q->from($table, $tableAlias);
		}

		// TODO: State expression
		$q->addSelect('"Exists" as state');

		$properties = $definition->getProperties();
		$pk = [];
		foreach ($properties as $property) {
			if ($property->hasExtension(SqlPropertyExtension::class)) {
				/** @var SqlPropertyExtension $ext */
				$ext = $property->getExtension(SqlPropertyExtension::class);
				$column = $ext->getSqlColumn();
				$sqlColumn = $this->db->quoteIdentifier($tableAlias) . '.' . $this->db->quoteIdentifier($column);

				if (!$stateOnly) {
					$q->addSelect($sqlColumn . ' AS ' . $this->db->quoteIdentifier($property->getName()));
				}

				if ($ext->isId()) {
					$pk[$property->getName()] = $sqlColumn;
				}
			}
			if (!$stateOnly && $property->hasExtension(SqlCalculatedPropertyExtension::class)) {
				/** @var SqlCalculatedPropertyExtension $ext */
				$ext = $property->getExtension(SqlCalculatedPropertyExtension::class);
				$expr = $ext->getSqlSelect();
				$q->addSelect("($expr) AS " . $this->db->quoteIdentifier($property->getName()));
			}
		}

		if (empty($pk)) {
			throw new LogicException('Missing primary key for ' . $definition->getMachineType());
		}
		foreach ($pk as $propertyName => $columnExpr) {
			$q->andWhere($columnExpr . ' = ?');
		}

		return $q;
	}


	/**
	 * Return the state of the refered state machine.
	 */
	public function getState($id): string
	{
		$q = $this->createQueryBuilder(true);
		$q->setParameter(0, $id);

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
		$q = $this->createQueryBuilder(false);
		$q->setParameter(0, $id);

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
