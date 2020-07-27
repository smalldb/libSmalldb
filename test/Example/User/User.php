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


/**
 * @StateMachine("user")
 * @UseRepository(UserRepository::class)
 * @UseTransitions(UserTransitions::class)
 * @WrapDTO(UserDataImmutable::class)
 * @SQL\StateSelect("'Exists'")
 */
abstract class User implements ReferenceInterface, UserData
{

	/**
	 * @State
	 * @Color("#def")
	 */
	const EXISTS = "Exists";


	/**
	 * @Transition("", {"Exists"})
	 * @Color("#4a0")
	 */
	abstract public function create(UserData $itemData);


	/**
	 * @Transition("Exists", {"Exists"})
	 */
	abstract public function update(UserData $itemData);


	/**
	 * @Transition("Exists", {""})
	 * @Color("#4a0")
	 */
	abstract public function delete();

}

