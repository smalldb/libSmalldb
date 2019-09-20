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


class SupervisorProcessData extends SupervisorProcessDataImmutable
{

	public function setId(int $id): void
	{
		$this->id = $id;
	}


	public function setState(string $state): void
	{
		$this->state = $state;
	}


	public function setCommand(string $command): void
	{
		$this->command = $command;
	}


	public function setCreatedAt(DateTimeImmutable $createdAt): void
	{
		$this->createdAt = $createdAt;
	}


	public function setModifiedAt(DateTimeImmutable $modifiedAt): void
	{
		$this->modifiedAt = $modifiedAt;
	}

}
