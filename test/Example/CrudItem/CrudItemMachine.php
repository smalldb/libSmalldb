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
namespace Smalldb\StateMachine\Test\Example\CrudItem;

use Smalldb\StateMachine\Annotation\StateMachine;
use Smalldb\StateMachine\Annotation\State;
use Smalldb\StateMachine\Annotation\Transition;
use Smalldb\StateMachine\ReferenceInterface;


/**
 * @StateMachine("crud-item")
 */
interface CrudItemMachine extends ReferenceInterface
{

	/**
	 * @State(color = "#baf")
	 */
	const EXISTS = "Exists";

	/**
	 * @Transition("", {"Exists"})
	 */
	public function create($itemData);

	/**
	 * @Transition("Exists", {"Exists"})
	 */
	public function update($itemData);

	/**
	 * @Transition("Exists", {""})
	 */
	public function delete();

}

