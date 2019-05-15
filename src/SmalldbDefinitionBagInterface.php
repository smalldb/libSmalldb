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

namespace Smalldb\StateMachine;

use Smalldb\StateMachine\Definition\StateMachineDefinition;


interface SmalldbDefinitionBagInterface
{

	/**
	 * Get the definition of the state machine.
	 */
	public function getDefinition(string $machineType): StateMachineDefinition;

	/**
	 * List of all machine types which have the definition in this bag.
	 *
	 * @return string[]
	 */
	public function getAllMachineTypes(): array;

	/**
	 * Returns array or generator of all state machine definitions.
	 * This may be slow and cause a lot of lazy-loading. It is better
	 * to use getAllMachineTypes() when possible.
	 *
	 * @return StateMachineDefinition[]
	 */
	public function getAllDefinitions(): iterable;

	/**
	 * List of all aliases which have the definition in this bag.
	 *
	 * @return string[]
	 */
	public function getAllAliases(): array;

}
