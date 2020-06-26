<?php declare(strict_types = 1);
/*
 * Copyright (c) 2019-2020, Josef Kufner  <josef@kufner.cz>
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

namespace Smalldb\StateMachine\Test\Example\SupervisorProcess;

use DateTimeImmutable;
use Smalldb\CodeCooker\Annotation\GenerateDTO;
use Smalldb\StateMachine\SqlExtension\Annotation\SQL;


/**
 * A process controlled by Supervisord
 *
 * @SQL\Table("supervisor_process")
 * @SQL\StateSelect("state")
 * @GenerateDTO("SupervisorProcessData")
 */
abstract class SupervisorProcessProperties
{

	/**
	 * @SQL\Id
	 */
	protected ?int $id = null;

	/**
	 * @SQL\Column
	 */
	protected string $state;

	/**
	 * @SQL\Column
	 */
	protected string $command;

	/**
	 * @SQL\Column("created_at")
	 */
	protected DateTimeImmutable $createdAt;

	/**
	 * @SQL\Column("modified_at")
	 */
	protected DateTimeImmutable $modifiedAt;

	/**
	 * @SQL\Column("memory_limit")
	 */
	protected ?int $memoryLimit;

	/**
	 * @var string[]|null
	 */
	protected ?array $args;

}
