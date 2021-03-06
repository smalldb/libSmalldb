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
use Smalldb\StateMachine\MachineIdentifierInterface;
use Symfony\Component\Security\Core\Security;


class IsOwnerCompiled implements PredicateCompiled
{
	private Security $security;
	private string $ownerProperty;


	public function __construct(string $ownerProperty, Security $security)
	{
		$this->security = $security;
		$this->ownerProperty = $ownerProperty;
	}


	public function evaluate(ReferenceInterface $ref): bool
	{
		$user = $this->security->getUser();

		// FIXME: Get the proper user ID.
		if ($user instanceof MachineIdentifierInterface) {
			$userId = $user->getMachineId();
		} else if (method_exists($user, 'getId')) {
			$userId = $user->getId();
		} else {
			$userId = $user->getUsername();
		}

		return $ref->get($this->ownerProperty) === $userId;
	}

}
