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
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Query\QueryBuilder as DoctrineQueryBuilder;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Provider\SmalldbProviderInterface;
use Smalldb\StateMachine\SqlExtension\SqlCalculatedPropertyExtension;
use Smalldb\StateMachine\SqlExtension\SqlPropertyExtension;
use Smalldb\StateMachine\SqlExtension\SqlTableExtension;


class ReferenceQueryBuilder extends DoctrineQueryBuilder
{

	/** @var SmalldbProviderInterface */
	private $machineProvider;

	/** @var StateMachineDefinition */
	private $definition;

	/** @var string */
	private $refClass;

	/** @var string */
	private $tableAlias = 'this';


	public function __construct(Connection $connection, SmalldbProviderInterface $machineProvider, string $tableAlias = 'this')
	{
		parent::__construct($connection);

		$this->machineProvider = $machineProvider;
		$this->definition = $this->machineProvider->getDefinition();
		$this->refClass = $this->machineProvider->getReferenceClass();
		$this->tableAlias = $tableAlias;
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
		$this->addSelect("($stateSelect) as state");

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
