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


	protected function create(TransitionEvent $transitionEvent, Post $ref, PostData $data): int
	{
		$id = $ref->getMachineId();

		$qb = $this->db->createQueryBuilder();
		$qb->insert($this->table)
			->values([
				'id' => $qb->createPositionalParameter($id),
				'author_id' => $qb->createPositionalParameter($data->getAuthorId()),
				'title' => $qb->createPositionalParameter($data->getTitle()),
				'slug' => $qb->createPositionalParameter($data->getSlug()),
				'summary' => $qb->createPositionalParameter($data->getSummary()),
				'content' => $qb->createPositionalParameter($data->getContent()),
				'published_at' => $qb->createPositionalParameter(($d = $data->getPublishedAt()) ? $d->format(DATE_ISO8601) : null),
			])
			->execute();

		if ($id === null) {
			$newId = (int) $this->db->lastInsertId();
			$transitionEvent->setNewId($newId);
			return $newId;
		} else {
			return $id;
		}
	}


	protected function update(TransitionEvent $transitionEvent, Post $ref, PostData $data): void
	{
		$oldId = $ref->getMachineId();
		$newId =  $data->getId();

		$qb = $this->db->createQueryBuilder();
		$affectedRows = $qb->update($this->table)
			->set('id', $qb->createPositionalParameter($newId))
			->set('author_id', $qb->createPositionalParameter($data->getAuthorId()))
			->set('title', $qb->createPositionalParameter($data->getTitle()))
			->set('slug', $qb->createPositionalParameter($data->getSlug()))
			->set('summary', $qb->createPositionalParameter($data->getSummary()))
			->set('content', $qb->createPositionalParameter($data->getContent()))
			->set('published_at', $qb->createPositionalParameter(($d = $data->getPublishedAt()) ? $d->format(DATE_ISO8601) : null))
			->where('id = ' . $qb->createPositionalParameter($oldId))
			->execute();

		if ($affectedRows > 0 && $oldId != $newId) {
			$transitionEvent->setNewId($newId);
		}
	}


	protected function delete(TransitionEvent $transitionEvent, Post $ref): void
	{
		$id = $ref->getMachineId();
		$qb = $this->db->createQueryBuilder();

		$affectedRows = $qb->delete($this->table)
			->andWhere("id = " . $qb->createNamedParameter($id))
			->execute();

		if ($affectedRows > 0) {
			$transitionEvent->setNewId(null);
		}
	}

}
