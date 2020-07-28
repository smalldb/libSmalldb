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

namespace Smalldb\StateMachine\AccessControlExtension\Predicate;

use Smalldb\StateMachine\ReferenceInterface;
use Symfony\Component\Security\Core\Security;


class HasRoleCompiled implements PredicateCompiled
{

	private string $role;
	private Security $security;


	public function __construct(string $role, Security $security)
	{
		$this->role = $role;
		$this->security = $security;
	}


	public function evaluate(ReferenceInterface $ref): bool
	{
		// FIXME: Is there a hasRole() method?
		return in_array($this->role, $this->security->getUser()->getRoles(), true);
	}

}
