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

namespace Smalldb\StateMachine\Test\Example\Post;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Smalldb\StateMachine\Test\Example\Post\PostData\PostData;
use Smalldb\StateMachine\Transition\MethodTransitionsDecorator;
use Smalldb\StateMachine\Transition\TransitionDecorator;
use Smalldb\StateMachine\Transition\TransitionEvent;


class PostTransitions extends MethodTransitionsDecorator implements TransitionDecorator
{
	private Connection $db;
	private string $table = 'symfony_demo_post';

	public function __construct(Connection $db)
	{
		parent::__construct();
		$this->db = $db;
	}


	/**
	 * @throws DBALException
	 */
	protected function create(TransitionEvent $transitionEvent, Post $ref, PostData $data): int
	{
		$stmt = $this->db->prepare("
			INSERT INTO $this->table (id, author_id, title, slug, summary, content, published_at)
			VALUES (:id, :authorId, :title, :slug, :summary, :content, :publishedAt)
		");

		$id = $ref->getMachineId();
		$stmt->execute([
			'id' => $id,
			'authorId' => $data->getAuthorId(),
			'title' => $data->getTitle(),
			'slug' => $data->getSlug(),
			'summary' => $data->getSummary(),
			'content' => $data->getContent(),
			'publishedAt' => ($d = $data->getPublishedAt()) ? $d->format(DATE_ISO8601) : null,
		]);

		if ($id === null) {
			$newId = (int) $this->db->lastInsertId();
			$transitionEvent->setNewId($newId);
			return $newId;
		} else {
			return $id;
		}
	}


	/**
	 * @throws DBALException
	 */
	protected function update(TransitionEvent $transitionEvent, Post $ref, PostData $data): void
	{
		$stmt = $this->db->prepare("
			UPDATE $this->table
			SET
				id = :newId,
				author_id = :authorId,
				title = :title,
				slug = :slug,
				summary = :summary,
				content = :content,
				published_at = :publishedAt
			WHERE
				id = :oldId
			LIMIT 1
		");

		$oldId = $ref->getMachineId();
		$newId =  $data->getId();
		$stmt->execute([
			'oldId' => $oldId,
			'newId' => $newId,
			'authorId' => $data->getAuthorId(),
			'title' => $data->getTitle(),
			'slug' => $data->getSlug(),
			'summary' => $data->getSummary(),
			'content' => $data->getContent(),
			'publishedAt' => ($d = $data->getPublishedAt()) ? $d->format(DATE_ISO8601) : null,
		]);

		if ($oldId != $newId) {
			$transitionEvent->setNewId($newId);
		}
	}


	/**
	 * @throws DBALException
	 */
	protected function delete(TransitionEvent $transitionEvent, Post $ref): void
	{
		$id = $ref->getMachineId();
		$stmt = $this->db->prepare("
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
