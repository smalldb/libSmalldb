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

namespace Smalldb\StateMachine\SqlExtension\Definition;

use Smalldb\StateMachine\Definition\ExtensionInterface;
use Smalldb\StateMachine\Utils\SimpleJsonSerializableTrait;


class SqlTableExtension implements ExtensionInterface
{
	use SimpleJsonSerializableTrait;

	private string $sqlTable;
	private string $sqlStateSelect;


	public function __construct(string $sqlTable, string $sqlStateSelect)
	{
		$this->sqlTable = $sqlTable;
		$this->sqlStateSelect = $sqlStateSelect;
	}


	public function getSqlTable(): string
	{
		return $this->sqlTable;
	}


	public function getSqlStateSelect(): string
	{
		return $this->sqlStateSelect;
	}

}
