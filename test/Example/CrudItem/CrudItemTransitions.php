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

use Smalldb\StateMachine\Test\Database\ArrayDao;
use Smalldb\StateMachine\Transition\MethodTransitionsDecorator;
use Smalldb\StateMachine\Transition\TransitionDecorator;
use Smalldb\StateMachine\Transition\TransitionEvent;


class CrudItemTransitions extends MethodTransitionsDecorator implements TransitionDecorator
{
	/** @var CrudItemRepository */
	private $repository;

	/** @var ArrayDao */
	private $dao;


	public function __construct(CrudItemRepository $repository, ArrayDao $dao)
	{
		parent::__construct();
		$this->repository = $repository;
		$this->dao = $dao;
	}

	protected function create(TransitionEvent $transitionEvent, CrudItemRef $ref, $data): int
	{
		$newId = $this->dao->create($data);
		$transitionEvent->setNewId($newId);
		return $newId;
	}

	protected function update(TransitionEvent $transitionEvent, CrudItemRef $ref, $data): void
	{
		$this->dao->update($ref->getId(), $data);
	}

	protected function delete(TransitionEvent $transitionEvent, CrudItemRef $ref): void
	{
		$this->dao->delete($ref->getId());
	}

}
