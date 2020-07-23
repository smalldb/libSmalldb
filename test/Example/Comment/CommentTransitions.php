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

namespace Smalldb\StateMachine\Test\Example\Comment;

use App\Events\CommentCreatedEvent;
use Smalldb\StateMachine\Test\Example\Comment\CommentData\CommentData;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Smalldb\StateMachine\Transition\MethodTransitionsDecorator;
use Smalldb\StateMachine\Transition\TransitionDecorator;
use Smalldb\StateMachine\Transition\TransitionEvent;
use Smalldb\StateMachine\Transition\TransitionGuard;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;


class CommentTransitions extends MethodTransitionsDecorator implements TransitionDecorator
{
	private Connection $db;
	private string $table = 'symfony_demo_comment';
	private ?EventDispatcherInterface $eventDispatcher;


	public function __construct(TransitionGuard $guard, Connection $db, ?EventDispatcherInterface $eventDispatcher)
	{
		parent::__construct($guard);
		$this->db = $db;
		$this->eventDispatcher = $eventDispatcher;
	}


	/**
	 * @throws DBALException
	 */
	protected function create(TransitionEvent $transitionEvent, Comment $ref, CommentData $commentData): int
	{
		$stmt = $this->db->prepare("
			INSERT INTO $this->table (id, content, post_id, author_id, published_at)
			VALUES (:id, :content, :post_id, :author_id, :published_at)
		");

		$id = $ref->getMachineId();
		$stmt->execute([
			'id' => $id,
			'content' => $commentData->getContent(),
			'post_id' => $commentData->getPostId(),
			'author_id' => $commentData->getAuthorId(),
			'published_at' => ($d = $commentData->getPublishedAt()) ? $d->format(DATE_ISO8601) : null,
		]);

		if ($id === null) {
			$newId = (int)$this->db->lastInsertId();
			$transitionEvent->setNewId($newId);
		}

		if ($this->eventDispatcher) {
			// When an event is dispatched, Symfony notifies it to all the listeners
			// and subscribers registered to it. Listeners can modify the information
			// passed in the event and they can even modify the execution flow, so
			// there's no guarantee that the rest of this controller will be executed.
			// See https://symfony.com/doc/current/components/event_dispatcher.html
			$this->eventDispatcher->dispatch(new CommentCreatedEvent($ref));
		}

		return $newId ?? $id;
	}

}
