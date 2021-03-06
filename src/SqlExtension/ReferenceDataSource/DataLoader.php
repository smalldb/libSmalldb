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

use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\Driver\Statement;
use Smalldb\StateMachine\ReferenceInterface;


class DataLoader extends DataSource
{

	public function fetch(Statement $stmt): ?ReferenceInterface
	{
		$row = $stmt->fetch(FetchMode::ASSOCIATIVE);
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
	public function fetchAll(Statement $stmt): array
	{
		$list = [];
		while (($row = $stmt->fetch(FetchMode::ASSOCIATIVE)) !== false) {
			$ref = new $this->refClass($this->smalldb, $this->machineProvider, $this);
			/** @noinspection PhpUndefinedMethodInspection */
			($this->refClass)::hydrateFromArray($ref, $row);
			$list[] = $ref;
		}
		return $list;
	}


	/**
	 * @return iterable<ReferenceInterface>
	 *
	 * TODO: Return a proper collection which provides rowCount() and other useful features.
	 */
	public function fetchAllIter(Statement $stmt): iterable
	{
		while (($row = $stmt->fetch(FetchMode::ASSOCIATIVE)) !== false) {
			$ref = new $this->refClass($this->smalldb, $this->machineProvider, $this);
			/** @noinspection PhpUndefinedMethodInspection */
			($this->refClass)::hydrateFromArray($ref, $row);
			yield $ref;
		}
	}

}
