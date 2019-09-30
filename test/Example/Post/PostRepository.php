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
use Smalldb\StateMachine\SqlExtension\ReferenceDataSource\ReferenceQueryResult;
use Smalldb\StateMachine\Test\Example\Tag\Tag;
use Smalldb\StateMachine\Test\Misc\AbstractCountingSqlRepository;


class PostRepository extends AbstractCountingSqlRepository implements SmalldbRepositoryInterface
{
	protected const REF_CLASS = Post::class;


	public function ref($id): Post
	{
		/** @var Post $ref */
		$ref = $this->getDataSource()->ref($id);
		return $ref;
	}


	public function findBySlug(string $slug): ?Post
	{
		$q = $this->getDataSource()->createQueryBuilder()
			->addSelectFromStatements();
		$q->where('slug = :slug');
		$q->setMaxResults(1);

		$q->setParameter('slug', $slug);

		$result = $q->executeRef();
		$this->onQuery($q);

		/** @var Post|null $post */
		$post = $result->fetch();
		return $post;
	}


	/**
	 * @return Post[]
	 */
	public function findLatest(int $page = 0, ?Tag $tag = null): ReferenceQueryResult
	{
		assert($page >= 0);

		$pageSize = 25;
		$pageOffset = $page * $pageSize;

		$q = $this->getDataSource()->createQueryBuilder()
			->addSelectFromStatements();
		$q->orderBy('published_at', 'DESC');
		$q->addOrderBy('id', 'DESC');
		$q->setFirstResult($pageOffset);
		$q->setMaxResults($pageSize);
		$result = $q->executeRef();
		$this->onQuery($q);

		return $result;
	}


	public function findAll(): ReferenceQueryResult
	{
		$q = $this->getDataSource()->createQueryBuilder()
			->addSelectFromStatements();
		$q->orderBy('published_at', 'DESC');
		$q->addOrderBy('id', 'DESC');
		$result = $q->executeRef();
		$this->onQuery($q);
		return $result;
	}

}

