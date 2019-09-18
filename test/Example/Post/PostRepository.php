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
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Query\QueryBuilder;
use Smalldb\StateMachine\Provider\SmalldbProviderInterface;
use Smalldb\StateMachine\ReferenceDataSource\DoctrineDBAL\DataLoader;
use Smalldb\StateMachine\ReferenceDataSource\DoctrineDBAL\DataSource;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\SmalldbRepositoryInterface;
use Smalldb\StateMachine\Test\Example\Tag\Tag;


class PostRepository implements SmalldbRepositoryInterface
{
	/** @var Smalldb */
	private $smalldb;

	/** @var SmalldbProviderInterface */
	private $machineProvider = null;

	/** @var string */
	private $refClass = null;

	/** @var Connection */
	private $db;

	/** @var DataSource */
	private $postDataSource = null;

	private $queryCount = 0;


	public function __construct(Smalldb $smalldb, Connection $db)
	{
		$this->smalldb = $smalldb;
		$this->db = $db;
	}


	public function getMachineProvider(): SmalldbProviderInterface
	{
		return $this->machineProvider ?? ($this->machineProvider = $this->smalldb->getMachineProvider(Post::class));
	}


	public function getReferenceClass(): string
	{
		return $this->refClass ?? ($this->refClass = $this->getMachineProvider()->getReferenceClass());
	}


	private function getPostDataSource(): DataSource
	{
		return $this->postDataSource ?? ($this->postDataSource = $this->createPostDataSource());
	}


	private function createPostDataSource(): DataSource
	{
		$dataSource = new DataSource(null, $this->smalldb, $this->smalldb->getMachineProvider(Post::class), $this->db);
		$dataSource->setOnQueryCallback(function(QueryBuilder $q) {
			$this->queryCount++;
		});
		return $dataSource;
	}


	public function getDataSourceQueryCount(): int
	{
		return $this->queryCount;
	}


	public function ref($id): Post
	{
		/** @var Post $ref */
		$ref = $this->getPostDataSource()->ref($id);
		return $ref;
	}


	public function findBySlug(string $slug): ?Post
	{
		$q = $this->getPostDataSource()->createQueryBuilder()
			->addSelectFromStatements();
		$q->where('slug = :slug');
		$q->setMaxResults(1);

		$q->setParameter('slug', $slug);

		$result = $q->executeRef();
		$this->queryCount++;

		/** @var Post|null $post */
		$post = $result->fetch();
		return $post;
	}


	/**
	 * @return Post[]
	 */
	public function findLatest(int $page = 0, ?Tag $tag = null): array
	{
		assert($page >= 0);

		$pageSize = 25;
		$pageOffset = $page * $pageSize;

		$q = $this->getPostDataSource()->createQueryBuilder()
			->addSelectFromStatements();
		$q->orderBy('published_at', 'DESC');
		$q->addOrderBy('id', 'DESC');
		$q->setFirstResult($pageOffset);
		$q->setMaxResults($pageSize);
		$result = $q->executeRef();

		$this->queryCount++;

		/** @var Post[] $posts */
		$posts = $result->fetchAll();
		return $posts;
	}


	public function findAll(): iterable
	{
		$q = $this->getPostDataSource()->createQueryBuilder()
			->addSelectFromStatements();
		$q->orderBy('published_at', 'DESC');
		$q->addOrderBy('id', 'DESC');
		$result = $q->executeRef();

		$this->queryCount++;

		$posts = $result->fetchAll();
		return $posts;
	}

}

