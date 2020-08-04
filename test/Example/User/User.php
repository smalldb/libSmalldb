<?php
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

namespace Smalldb\StateMachine\Test\Example\User;

use Smalldb\SmalldbBundle\Security\UserProxy;
use Smalldb\SmalldbBundle\Security\UserReferenceInterface;
use Smalldb\StateMachine\AccessControlExtension\Annotation\AC;
use Smalldb\StateMachine\Annotation\State;
use Smalldb\StateMachine\Annotation\StateMachine;
use Smalldb\StateMachine\Annotation\Transition;
use Smalldb\StateMachine\Annotation\UseRepository;
use Smalldb\StateMachine\Annotation\UseTransitions;
use Smalldb\StateMachine\DtoExtension\Annotation\WrapDTO;
use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\SqlExtension\Annotation\SQL;
use Smalldb\StateMachine\StyleExtension\Annotation\Color;
use Smalldb\StateMachine\Test\Example\User\UserData\UserData;
use Smalldb\StateMachine\Test\Example\User\UserData\UserDataImmutable;
use Symfony\Component\Security\Core\User\UserInterface;


/**
 * @StateMachine("user")
 * @UseRepository(UserRepository::class)
 * @UseTransitions(UserTransitions::class)
 * @WrapDTO(UserDataImmutable::class)
 * @SQL\StateSelect("'Exists'")
 * @AC\DefinePolicy("Admin", @AC\IsGranted("ROLE_ADMIN"))
 * @AC\DefinePolicy("Self", @AC\SomeOf(@AC\IsOwner("id"), @AC\IsGranted("ROLE_ADMIN")))
 * @AC\DefaultPolicy("Admin")
 */
abstract class User implements ReferenceInterface, UserData /* UserReferenceInterface */ /* UserInterface */
{

	/**
	 * @State
	 * @Color("#def")
	 */
	const EXISTS = "Exists";


	/**
	 * @Transition("", {"Exists"})
	 * @Color("#888")
	 */
	abstract public function register(UserData $itemData, string $plainPassword);


	/**
	 * @Transition("Exists", {"Exists"})
	 * @AC\UsePolicy("Self")
	 */
	abstract public function updateProfile(UserData $itemData);


	/**
	 * @Transition("Exists", {"Exists"})
	 * @AC\UsePolicy("Self")
	 */
	abstract public function changePassword(string $newPassword);


	/**
	 * @Transition("Exists", {""})
	 * @Color("#888")
	 */
	abstract public function delete();


	public function getSalt(): string
	{
		return "";
	}

	public function eraseCredentials()
	{
		throw new \LogicException("This should not happen. Use " . UserProxy::class . " instead.");
	}

}

