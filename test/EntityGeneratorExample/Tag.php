<?php declare(strict_types = 1);
/*
 * Copyright (c) 2020, Josef Kufner  <josef@kufner.cz>
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

namespace Smalldb\StateMachine\Test\EntityGeneratorExample;

use Smalldb\StateMachine\CodeGenerator\InferClass\Annotation\InferSmalldbEntity;
use Smalldb\StateMachine\CodeGenerator\InferClass\Annotation\PublicMutator;
use Smalldb\StateMachine\SqlExtension\Annotation\SQL;


/**
 * Tag - An entity based on Symfony Demo
 *
 * @SQL\Table("symfony_demo_post")
 * @InferSmalldbEntity()
 */
abstract class Tag
{

	/**
	 * @SQL\Id
	 */
	protected ?int $id;

	/**
	 * @SQL\Column
	 */
	protected string $name;


	public function getSlug(): string
	{
		return preg_replace('/[^a-z0-9]+/', '-', strtolower($this->name));
	}


	/**
	 * @PublicMutator
	 */
	protected function setNameFromSlug(string $slug): string
	{
		return ($this->name = ucfirst(str_replace('-', ' ', $slug)));
	}


	/**
	 * @PublicMutator
	 */
	protected function resetName(): void
	{
		$this->name = (string) $this->id;
	}

}
