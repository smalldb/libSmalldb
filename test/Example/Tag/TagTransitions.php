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

use PDO;
use Smalldb\StateMachine\Transition\MethodTransitionsDecorator;
use Smalldb\StateMachine\Transition\TransitionDecorator;
use Smalldb\StateMachine\Transition\TransitionEvent;


class TagTransitions extends MethodTransitionsDecorator implements TransitionDecorator
{
	/** @var PDO */
	private $pdo;
	private $table = 'symfony_demo_tag';

	public function __construct(PDO $db)
	{
		parent::__construct();
		$this->pdo = $db;
	}


	protected function create(TransitionEvent $transitionEvent, Tag $ref, TagDataImmutable $data): int
	{
		$stmt = $this->pdo->prepare("
			INSERT INTO $this->table (id, name)
			VALUES (:id, :name)
		");

		$id = $ref->getMachineId();
		$stmt->execute([
			'id' => $id,
			'name' => $data->getName(),
		]);

		if ($id === null) {
			$newId = (int) $this->pdo->lastInsertId();
			$transitionEvent->setNewId($newId);
			return $newId;
		} else {
			return $id;
		}
	}


	protected function update(TransitionEvent $transitionEvent, Tag $ref, TagData $data): void
	{
		$stmt = $this->pdo->prepare("
			UPDATE $this->table
			SET
				id = :newId,
				name = :name,
			WHERE
				id = :oldId
			LIMIT 1
		");

		$oldId = $ref->getId();
		$newId =  $data->getId();
		$stmt->execute([
			'oldId' => $oldId,
			'newId' => $newId,
			'name' => $data->getName(),
		]);

		if ($oldId != $newId) {
			$transitionEvent->setNewId($newId);
		}
	}


	protected function delete(TransitionEvent $transitionEvent, Tag $ref): void
	{
		$id = $ref->getId();
		$stmt = $this->pdo->prepare("
			DELETE FROM $this->table
			WHERE id = :id
			LIMIT 1
		");
		$stmt->execute([
			'id' => $id,
		]);
		$transitionEvent->setNewId(null);
	}


}
