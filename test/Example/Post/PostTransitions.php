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
use Doctrine\DBAL\FetchMode;
use Smalldb\StateMachine\Test\Example\Comment\Comment;
use Smalldb\StateMachine\Test\Example\Comment\CommentData\CommentData;
use Smalldb\StateMachine\Test\Example\Comment\CommentRepository;
use Smalldb\StateMachine\Test\Example\Post\PostData\PostData;
use Smalldb\StateMachine\Test\Example\Tag\TagData\TagData;
use Smalldb\StateMachine\Test\Example\Tag\TagRepository;
use Smalldb\StateMachine\Transition\MethodTransitionsDecorator;
use Smalldb\StateMachine\Transition\TransitionDecorator;
use Smalldb\StateMachine\Transition\TransitionEvent;
use Smalldb\StateMachine\Transition\TransitionGuard;
use Symfony\Component\String\Slugger\SluggerInterface;


class PostTransitions extends MethodTransitionsDecorator implements TransitionDecorator
{
	private Connection $db;
	private CommentRepository $commentRepository;
	private SluggerInterface $slugger;
	private TagRepository $tagRepository;


	public function __construct(TransitionGuard $guard, Connection $db, TagRepository $tagRepository, CommentRepository $commentRepository, SluggerInterface $slugger)
	{
		parent::__construct($guard);
		$this->db = $db;
		$this->commentRepository = $commentRepository;
		$this->slugger = $slugger;
		$this->tagRepository = $tagRepository;
	}


	/**
	 * @throws DBALException
	 */
	protected function create(TransitionEvent $transitionEvent, Post $ref, PostData $data, ?array $tags = null): int
	{
		$this->db->beginTransaction();

		$stmt = $this->db->prepare("
			INSERT INTO symfony_demo_post (id, author_id, title, slug, summary, content, published_at)
			VALUES (:id, :authorId, :title, :slug, :summary, :content, :publishedAt)
		");

		$slug = $this->slugger->slug($data->getTitle());

		$id = $ref->getMachineId();
		$stmt->execute([
			'id' => $id,
			'authorId' => $data->getAuthorId(),
			'title' => $data->getTitle(),
			'slug' => $slug,
			'summary' => $data->getSummary(),
			'content' => $data->getContent(),
			'publishedAt' => ($d = $data->getPublishedAt()) ? $d->format(DATE_ISO8601) : null,
		]);

		if ($id === null) {
			$newId = (int)$this->db->lastInsertId();
			if ($tags !== null) {
				$this->assignTags($newId, $tags);
			}
			$this->db->commit();
			$transitionEvent->setNewId($newId);
			return $newId;
		} else {
			$this->db->commit();
			return $id;
		}
	}


	/**
	 * @throws DBALException
	 */
	protected function update(TransitionEvent $transitionEvent, Post $ref, PostData $data, ?array $tags = null): void
	{
		$this->db->beginTransaction();

		$stmt = $this->db->prepare("
			UPDATE symfony_demo_post
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
		");

		$oldId = $ref->getMachineId();
		$newId = $data->getId();

		$slug = $this->slugger->slug($data->getTitle());

		$stmt->execute([
			'oldId' => $oldId,
			'newId' => $newId,
			'authorId' => $data->getAuthorId(),
			'title' => $data->getTitle(),
			'slug' => $slug,
			'summary' => $data->getSummary(),
			'content' => $data->getContent(),
			'publishedAt' => ($d = $data->getPublishedAt()) ? $d->format(DATE_ISO8601) : null,
		]);

		if ($tags !== null) {
			$this->assignTags($newId, $tags);
		}
		$this->db->commit();

		if ($oldId != $newId) {
			$transitionEvent->setNewId($newId);
		}
	}


	/**
	 * @param TagData[] $tags
	 * @return int Number of changed rows.
	 */
	private function assignTags($postId, array $tags): int
	{
		$tagIds = [];
		$createTagIds = [];
		foreach ($tags as $t) {
			if ($t->getId() === null) {
				// If tag has no ID, then create it.
				$tRef = $this->tagRepository->ref(null);
				$tRef->create($t);
				$createTagIds[] = $tRef->getId();
			} else {
				// Collect list of IDs that post should already have
				$tagIds[] = $t->getId();
			}
		}

		$oldTagIds = $this->db->createQueryBuilder()->select("tag_id")
			->from("symfony_demo_post_tag")
			->where("post_id = :postId")
			->setParameter('postId', $postId)
			->execute()
			->fetchAll(FetchMode::COLUMN);

		// Calculate the differences
		$removeIds = array_diff($oldTagIds, $tagIds);
		$insertIds = array_diff($tagIds, $oldTagIds);
		$newIds = array_merge($insertIds, $createTagIds);

		$rows = 0;

		// Delete tags that the post should not have
		if (!empty($removeIds)) {
			$rows += $this->db->createQueryBuilder()->delete("symfony_demo_post_tag")
				->andWhere("tag_id IN (:tagIds)")
				->andWhere("post_id = :postId")
				->setParameter(":tagIds", $removeIds, Connection::PARAM_STR_ARRAY)
				->setParameter(":postId", $postId)
				->execute();
		}

		// Assign the existing and created tags
		if (!empty($newIds)) {
			$iq = $this->db->createQueryBuilder()
				->insert("symfony_demo_post_tag")
				->values(['post_id' => ':postId', 'tag_id' => ':tagId']);
			$insertStmt = $this->db->prepare($iq->getSQL());
			$insertStmt->bindValue(':postId', $postId);
			foreach ($newIds as $tagId) {
				$insertStmt->bindValue(':tagId', $tagId);
				$rows += $insertStmt->execute();
			}
		}

		return $rows;
	}


	protected function addComment(TransitionEvent $transitionEvent, Post $ref, CommentData $commentData): Comment
	{
		$commentRef = $this->commentRepository->ref(null);
		$commentRef->create($commentData);
		return $commentRef;
	}


	/**
	 * @throws DBALException
	 */
	protected function delete(TransitionEvent $transitionEvent, Post $ref): void
	{
		$postId = $ref->getMachineId();

		$this->db->beginTransaction();

		// Delete the tags associated with this blog post. This should be done
		// automatically, except for SQLite (the database used in this application)
		// because foreign key support is not enabled by default in SQLite
		$this->db->prepare("
			DELETE FROM symfony_demo_post_tag
			WHERE post_id = :postId
		")->execute(['postId' => $postId]);

		$stmt = $this->db->prepare("
			DELETE FROM symfony_demo_post
			WHERE id = :id
		");
		$stmt->execute([
			'id' => $postId,
		]);
		$rowCount = $stmt->rowCount();
		$this->db->commit();

		if ($rowCount === 1) {
			$transitionEvent->setNewId(null);
		}
	}

}
