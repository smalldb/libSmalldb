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

use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Smalldb;


/**
 * Class CrudItemRef
 * TODO: Implement \Smalldb\StateMachine\Reference ?
 */
class CrudItemRef implements CrudItemMachine
{
	/** @var Smalldb */
	private $smalldb;

	/** @var CrudItemRepository */
	private $repository;

	private $id;

	public function __construct(Smalldb $smalldb, ...$id)
	{
		$this->smalldb = $smalldb;
		$this->repository = $smalldb->getMachineProvider('crud-item')->getRepository();
		$this->id = $id[0] ?? null;
	}

	public function create($itemData)
	{
		// TODO: Implement create() method.
	}

	public function update($itemData)
	{
		// TODO: Implement update() method.
	}

	public function delete()
	{
		// TODO: Implement delete() method.
	}

	/**
	 * Read state machine state
	 */
	public function getState(): string
	{
		return $this->repository->getState($this->id);
	}

	/**
	 * Get state machine definition
	 */
	public function getDefinition(): StateMachineDefinition
	{
		// TODO: Implement getDefinition() method.
	}

	/**
	 * Invoke transition of the state machine.
	 */
	public function invokeTransition(string $transitionName, array $args = [])
	{
		// TODO: Implement invokeTransition() method.
	}
}
