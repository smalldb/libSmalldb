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

namespace Smalldb\StateMachine\Definition;

use Smalldb\StateMachine\Annotation\State;
use Smalldb\StateMachine\Annotation\Transition;
use Smalldb\StateMachine\ReferenceInterface;


/**
 * CrudMachine -- a basic CRUD state machine definition.
 */
interface CrudMachineDefinition extends ReferenceInterface
{

	/**
	 * @State
	 */
	const EXISTS = "Exists";

	/**
	 * @Transition("", {"Exists"})
	 */
	public function create($data);

	/**
	 * @Transition("Exists", {"Exists"})
	 */
	public function update($data);

	/**
	 * @Transition("Exists", {""})
	 */
	public function delete();

}

