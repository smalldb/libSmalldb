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

use Psr\Log\LoggerInterface;
use Smalldb\StateMachine\ReferenceInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;


class IsGrantedCompiled implements PredicateCompiled
{
	private string $attribute;
	private AuthorizationCheckerInterface $authorizationChecker;


	public function __construct(string $attribute, AuthorizationCheckerInterface $authorizationChecker)
	{
		$this->attribute = $attribute;
		$this->authorizationChecker = $authorizationChecker;
	}


	/**
	 * @see Twig's is_granted from \Symfony\Bridge\Twig\Extension\SecurityExtension.
	 */
	public function evaluate(ReferenceInterface $ref): bool
	{
		try {
			return $this->authorizationChecker->isGranted($this->attribute, $ref);
		}
		catch (AuthenticationCredentialsNotFoundException $ex) {
			return false;
		}
	}

}
