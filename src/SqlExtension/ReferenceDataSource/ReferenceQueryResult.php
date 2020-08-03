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
use Doctrine\DBAL\FetchMode;
use IteratorAggregate;
use Smalldb\StateMachine\ReferenceInterface;
use Traversable;


class ReferenceQueryResult extends DataSource implements IteratorAggregate
{
	private Statement $stmt;


	public function __construct(?DataSource $originalDataSource, Statement $stmt)
	{
		parent::__construct($originalDataSource);
		$this->stmt = $stmt;
	}


	public function getWrappedStatement(): Statement
	{
		return $this->stmt;
	}


	public function fetch(): ?ReferenceInterface
	{
		$row = $this->stmt->fetch(FetchMode::ASSOCIATIVE);
		if ($row === false) {
			return null;
		} else {
			$ref = new $this->refClass($this->smalldb, $this->machineProvider, $this);
			/** @noinspection PhpUndefinedMethodInspection */
			($this->refClass)::hydrateFromArray($ref, $row);
			return $ref;
		}
	}


	/**
	 * @return ReferenceInterface[]
	 */
	public function fetchAll(): array
	{
		$list = [];
		while (($row = $this->stmt->fetch(FetchMode::ASSOCIATIVE)) !== false) {
			$ref = new $this->refClass($this->smalldb, $this->machineProvider, $this);
			/** @noinspection PhpUndefinedMethodInspection */
			($this->refClass)::hydrateFromArray($ref, $row);
			$list[] = $ref;
		}
		return $list;
	}


	public function getIterator(): Traversable
	{
		while (($row = $this->stmt->fetch(FetchMode::ASSOCIATIVE)) !== false) {
			$ref = new $this->refClass($this->smalldb, $this->machineProvider, $this);
			/** @noinspection PhpUndefinedMethodInspection */
			($this->refClass)::hydrateFromArray($ref, $row);
			yield $ref;
		}
	}


	public function map(callable $mapFunction): Traversable
	{
		foreach ($this->getIterator() as $k => $item) {
			yield $k => $mapFunction($item);
		}
	}


	public function join(string $separator, ?callable $mapFunction = null): string
	{
		$iter = $this->getIterator();
		$str = '';
		if ($mapFunction) {
			foreach ($iter as $first) {
				$str = (string)$mapFunction($first);
				break;
			}
			foreach ($iter as $item) {
				$str .= $separator;
				$str .= (string)$mapFunction($item);
			}
			return $str;
		} else {
			foreach ($iter as $first) {
				$str = (string)$first;
				break;
			}
			foreach ($iter as $item) {
				$str .= $separator;
				$str .= $item;
			}
			return $str;
		}
	}

}
