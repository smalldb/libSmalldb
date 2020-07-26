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

namespace Smalldb\StateMachine\AccessControlExtension\Definition;

use Smalldb\StateMachine\AccessControlExtension\Predicate\Predicate;


class AccessControlPolicy
{
	private string $name;
	private Predicate $predicate;


	public function __construct(string $name, Predicate $predicate)
	{
		$this->name = $name;
		$this->predicate = $predicate;
	}


	public function getName(): string
	{
		return $this->name;
	}


	public function getPredicate(): Predicate
	{
		return $this->predicate;
	}

}
