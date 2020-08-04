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

namespace Smalldb\StateMachine\Test\Example\Tag;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Smalldb\StateMachine\Test\Example\Tag\TagData\TagData;
use Smalldb\StateMachine\Transition\MethodTransitionsDecorator;
use Smalldb\StateMachine\Transition\TransitionDecorator;
use Smalldb\StateMachine\Transition\TransitionEvent;
use Smalldb\StateMachine\Transition\TransitionGuard;


class TagTransitions extends MethodTransitionsDecorator implements TransitionDecorator
{
	private Connection $db;


	public function __construct(TransitionGuard $guard, Connection $db)
	{
		parent::__construct($guard);
		$this->db = $db;
	}


	/**
	 * @throws DBALException
	 */
	protected function create(TransitionEvent $transitionEvent, Tag $ref, TagData $data): int
	{
		$stmt = $this->db->prepare("
			INSERT INTO symfony_demo_tag (id, name)
			VALUES (:id, :name)
		");

		$id = $ref->getMachineId();
		$stmt->execute([
			'id' => $id,
			'name' => $data->getName(),
		]);

		if ($id === null) {
			$newId = (int)$this->db->lastInsertId();
			$transitionEvent->setNewId($newId);
			return $newId;
		} else {
			return $id;
		}
	}


	/**
	 * @throws DBALException
	 */
	protected function update(TransitionEvent $transitionEvent, Tag $ref, TagData $data): void
	{
		$stmt = $this->db->prepare("
			UPDATE symfony_demo_tag
			SET
				id = :newId,
				name = :name
			WHERE
				id = :oldId
		");

		$oldId = $ref->getId();
		$newId = $data->getId();
		$stmt->execute([
			'oldId' => $oldId,
			'newId' => $newId,
			'name' => $data->getName(),
		]);

		if ($oldId != $newId) {
			$transitionEvent->setNewId($newId);
		}
	}


	/**
	 * @throws DBALException
	 */
	protected function delete(TransitionEvent $transitionEvent, Tag $ref): void
	{
		$id = $ref->getId();
		$stmt = $this->db->prepare("
			DELETE FROM symfony_demo_tag
			WHERE id = :id
		");
		$stmt->execute([
			'id' => $id,
		]);
		$transitionEvent->setNewId(null);
	}

}
