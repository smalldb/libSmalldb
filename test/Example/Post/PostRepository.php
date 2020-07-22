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

use Smalldb\StateMachine\SmalldbRepositoryInterface;
use Smalldb\StateMachine\SqlExtension\AbstractSqlRepository;
use Smalldb\StateMachine\SqlExtension\ReferenceDataSource\ReferenceQueryResult;
use Smalldb\StateMachine\Test\Example\Tag\Tag;
use function Symfony\Component\String\u;


class PostRepository extends AbstractSqlRepository implements SmalldbRepositoryInterface
{
	protected const REF_CLASS = Post::class;

	/**
	 * Use constants to define configuration options that rarely change instead
	 * of specifying them under parameters section in config/services.yaml file.
	 *
	 * See https://symfony.com/doc/current/best_practices.html#use-constants-to-define-options-that-rarely-change
	 */
	public const NUM_ITEMS = 10;


	public function ref($id): Post
	{
		/** @var Post $ref */
		$ref = $this->getDataSource()->ref($id);
		return $ref;
	}


	public function findBySlug(string $slug): ?Post
	{
		$q = $this->createQueryBuilder();
		$q->where('slug = :slug');
		$q->setMaxResults(1);

		$q->setParameter('slug', $slug);

		$result = $q->executeRef();

		/** @var Post|null $post */
		$post = $result->fetch();
		return $post;
	}


	/**
	 * @return Post[]
	 */
	public function findLatest(int $page = 1, ?Tag $tag = null): ReferenceQueryResult
	{
		assert($page >= 1);

		$pageSize = self::NUM_ITEMS;

		$q = $this->createQueryBuilder();
		$q->orderBy('published_at', 'DESC');
		$q->addOrderBy('id', 'DESC');

		if ($tag) {
			$q->andWhere('EXISTS(SELECT * FROM symfony_demo_post_tag pt WHERE pt.post_id = this.id AND pt.tag_id = :tag)')
				->setParameter('tag', $tag->getId());
		}

		$result = $q->execPaginateRef($page, $pageSize);
		return $result;
	}


	public function findAll(): ReferenceQueryResult
	{
		$q = $this->createQueryBuilder();
		$q->orderBy('published_at', 'DESC');
		$q->addOrderBy('id', 'DESC');
		$result = $q->executeRef();
		return $result;
	}


	/**
	 * @return Post[]
	 */
	public function findBySearchQuery(string $query, int $limit = self::NUM_ITEMS): ReferenceQueryResult
	{
		$searchTerms = $this->extractSearchTerms($query);

		$queryBuilder = $this->createQueryBuilder();

		if (empty($searchTerms)) {
			// Empty result set, but keep the structure of the result.
			$queryBuilder->andWhere('FALSE');
		}
		else foreach ($searchTerms as $key => $term) {
			$queryBuilder
				->orWhere('this.title LIKE :t_' . $key)
				->setParameter('t_' . $key, '%' . $term . '%');
		}

		return $queryBuilder
			->orderBy('this.published_at', 'DESC')
			->setMaxResults($limit)
			->executeRef();
	}


	/**
	 * Transforms the search string into an array of search terms.
	 */
	private function extractSearchTerms(string $searchQuery): array
	{
		$searchQuery = u($searchQuery)->replaceMatches('/[[:space:]]+/', ' ')->trim();
		$terms = array_unique($searchQuery->split(' '));

		// ignore the search terms that are too short
		return array_filter($terms, function ($term) {
			return 2 <= $term->length();
		});
	}

}

