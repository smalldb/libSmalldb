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

use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Query\QueryBuilder as DoctrineQueryBuilder;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Provider\SmalldbProviderInterface;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\SqlExtension\Definition\SqlCalculatedPropertyExtension;
use Smalldb\StateMachine\SqlExtension\Definition\SqlPropertyExtension;
use Smalldb\StateMachine\SqlExtension\Definition\SqlTableExtension;


class ReferenceQueryBuilder extends DoctrineQueryBuilder
{
	private Smalldb $smalldb;
	private SmalldbProviderInterface $machineProvider;
	private StateMachineDefinition $definition;
	private string $refClass;
	private DataSource $dataSource;
	private string $tableAlias;


	public function __construct(Smalldb $smalldb, SmalldbProviderInterface $machineProvider, DataSource $dataSource, string $tableAlias = 'this')
	{
		parent::__construct($dataSource->getConnection());

		$this->smalldb = $smalldb;
		$this->machineProvider = $machineProvider;
		$this->definition = $this->machineProvider->getDefinition();
		$this->refClass = $this->machineProvider->getReferenceClass();
		$this->dataSource = $dataSource;
		$this->tableAlias = $tableAlias;
	}


	public function executeRef(): ReferenceQueryResult
	{
		$stmt = parent::execute();
		if ($stmt instanceof Statement) {
			// Wrap statement with something we can use to fetch references
			return new ReferenceQueryResult($this->dataSource, $stmt);
		} else {
			throw new LogicException("Executed statement returns unexpected result.");
		}
	}


	public function execPaginateRef(int $page, int $pageSize): ReferenceQueryResultPaginated
	{
		if (!($page >= 1 && $page <= PHP_INT_MAX)) {
			throw new OutOfBoundsException("Invalid page: " . var_export($page, true));
		}
		if (!($pageSize >= 1 && $pageSize <= PHP_INT_MAX)) {
			throw new OutOfBoundsException("Invalid page size: " . var_export($pageSize, true));
		}
		if (!empty($this->getQueryPart('having'))) {
			throw new UnsupportedQueryException("HAVING clause not supported when paginating.");
		}
		if (!empty($this->getQueryPart('groupBy'))) {
			throw new UnsupportedQueryException("GROUP BY clause not supported when paginating.");
		}

		// Get result size
		$countQuery = clone $this;
		$countQuery->select('COUNT(*)');
		$countQuery->setFirstResult(null);
		$countQuery->setMaxResults(null);
		$stmt = $countQuery->execute();
		$resultCount = (int) $stmt->fetchColumn();

		// Get result statement
		$this->setFirstResult(($page - 1) * $pageSize);
		$this->setMaxResults($pageSize);
		$stmt = parent::execute();

		if ($stmt instanceof Statement) {
			// Wrap statement with something we can use to fetch references
			return new ReferenceQueryResultPaginated($this->dataSource, $stmt, $resultCount, $page, $pageSize);
		} else {
			throw new LogicException("Executed statement returns unexpected result.");
		}
	}


	public function quoteIdentifier(string $identifier): string
	{
		return $this->getConnection()->quoteIdentifier($identifier);
	}


	public function addSelectFromStatements(bool $stateOnly = false): self
	{
		if (!$this->definition->hasExtension(SqlTableExtension::class)) {
			throw new LogicException("SQL Table not specified for " . $this->definition->getMachineType());
		}

		/** @var SqlTableExtension $ext */
		$ext = $this->definition->getExtension(SqlTableExtension::class);
		$table = $ext->getSqlTable();
		$this->from($table, $this->tableAlias);
		$stateSelect = $ext->getSqlStateSelect();

		if ($stateSelect !== null) {
			$this->addSelect("($stateSelect) as state");
		} else if ($stateOnly) {
			throw new LogicException("Trying to read state using SQL query but the state select is not configured.");
		}

		$properties = $this->definition->getProperties();
		foreach ($properties as $property) {
			if ($property->hasExtension(SqlPropertyExtension::class)) {
				/** @var SqlPropertyExtension $ext */
				$ext = $property->getExtension(SqlPropertyExtension::class);
				$column = $ext->getSqlColumn();
				$sqlColumn = $this->quoteIdentifier($this->tableAlias) . '.' . $this->quoteIdentifier($column);

				if (!$stateOnly) {
					$this->addSelect($sqlColumn . ' AS ' . $this->quoteIdentifier($property->getName()));
				}
			}
			if (!$stateOnly && $property->hasExtension(SqlCalculatedPropertyExtension::class)) {
				/** @var SqlCalculatedPropertyExtension $ext */
				$ext = $property->getExtension(SqlCalculatedPropertyExtension::class);
				$expr = $ext->getSqlSelect();
				$this->addSelect("($expr) AS " . $this->quoteIdentifier($property->getName()));
			}
		}

		return $this;
	}


	public function andWhereId($id): self
	{
		$definition = $this->machineProvider->getDefinition();
		$properties = $definition->getProperties();

		if ($definition->hasExtension(SqlTableExtension::class)) {
			/** @var SqlTableExtension $ext */
			$ext = $definition->getExtension(SqlTableExtension::class);
			$table = $ext->getSqlTable();
			$this->from($table, $this->tableAlias);
		}

		$pk = [];
		foreach ($properties as $property) {
			if ($property->hasExtension(SqlPropertyExtension::class)) {
				/** @var SqlPropertyExtension $ext */
				$ext = $property->getExtension(SqlPropertyExtension::class);
				$column = $ext->getSqlColumn();
				$sqlColumn = $this->quoteIdentifier($this->tableAlias) . '.' . $this->quoteIdentifier($column);

				if ($ext->isId()) {
					$pk[$property->getName()] = $sqlColumn;
				}
			}
		}

		if (empty($pk)) {
			throw new LogicException('Missing primary key for ' . $definition->getMachineType());
		}
		foreach ($pk as $propertyName => $columnExpr) {
			$this->andWhere($columnExpr . ' = ' . $this->createNamedParameter($id));
		}

		return $this;
	}


	public function getTableAlias(): string
	{
		return $this->tableAlias;
	}

}
