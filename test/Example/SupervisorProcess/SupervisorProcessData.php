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

namespace Smalldb\StateMachine\Test\Example\SupervisorProcess;

use DateTimeImmutable;
use Smalldb\StateMachine\CodeGenerator\Annotation\InferSmalldbEntity;
use Smalldb\StateMachine\SqlExtension\Annotation as SQL;
use Smalldb\StateMachine\Utils\CopyConstructorTrait;


/**
 * A process controlled by Supervisord
 *
 * @SQL\Table("supervisor_process")
 * @SQL\StateSelect("state")
 * @InferSmalldbEntity()
 */
abstract class SupervisorProcessData
{
	use CopyConstructorTrait;

	/**
	 * @var int
	 * @SQL\Id
	 */
	protected $id;

	/**
	 * @var string
	 * @SQL\Column
	 */
	protected $state;

	/**
	 * @var string
	 * @SQL\Column
	 */
	protected $command;

	/**
	 * @var DateTimeImmutable
	 * @SQL\Column("created_at")
	 */
	protected $createdAt;

	/**
	 * @var DateTimeImmutable
	 * @SQL\Column("modified_at")
	 */
	protected $modifiedAt;

	/**
	 * @var ?int
	 * @SQL\Column("memory_limit")
	 */
	protected $memoryLimit;

	/**
	 * @var string[]|null
	 */
	protected $args;

}
