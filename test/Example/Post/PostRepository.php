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
use PDOStatement;
use Smalldb\StateMachine\Provider\SmalldbProviderInterface;
use Smalldb\StateMachine\ReferenceDataSource\DoctrineDbalDataLoader;
use Smalldb\StateMachine\ReferenceDataSource\PdoDataLoader;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\SmalldbRepositoryInterface;
use Smalldb\StateMachine\SqlExtension\SqlCalculatedPropertyExtension;
use Smalldb\StateMachine\SqlExtension\SqlPropertyExtension;
use Smalldb\StateMachine\SqlExtension\SqlTableExtension;
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

	/** @var DoctrineDbalDataLoader */
	private $postDataLoader = null;

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


	private function getPostDataLoader(): DoctrineDbalDataLoader
	{
		return $this->postDataLoader ?? ($this->postDataLoader = $this->createPostDataLoader());
	}


	private function prepareQuery(string $sqlQuery): Statement
	{
		try {
			return $this->db->prepare($sqlQuery);
		}
		catch(DBALException $ex) {
			// Re-throw the exception with SQL query attached to the message
			throw new DBALException($ex->getMessage() . "\n" . $sqlQuery, 0, $ex);
		}
	}


	private function getSqlTable(): string
	{
		$machineDefinition = $this->getMachineProvider()->getDefinition();

		/** @var SqlTableExtension $ext */
		$ext = $machineDefinition->getExtension(SqlTableExtension::class);
		return $ext->getSqlTable();
	}


	private function getSelectColumns(): string
	{
		$selectColumns = ['"Exists" as state'];
		$machineDefinition = $this->getMachineProvider()->getDefinition();
		foreach ($machineDefinition->getProperties() as $property) {
			if ($property->hasExtension(SqlPropertyExtension::class)) {
				/** @var SqlPropertyExtension $ext */
				$ext = $property->getExtension(SqlPropertyExtension::class);
				$column = $ext->getSqlColumn();
				$selectColumns[] = "this." . $column . " as " . $property->getName();
			}
			if ($property->hasExtension(SqlCalculatedPropertyExtension::class)) {
				/** @var SqlCalculatedPropertyExtension $ext */
				$ext = $property->getExtension(SqlCalculatedPropertyExtension::class);
				$selectExpr = $ext->getSqlSelect();
				$selectColumns[] = "(" . $selectExpr . ") as " . $property->getName();
			}
		}
		return join(', ', $selectColumns);
	}


	private function createPostDataLoader($preloadedDataSet = null): DoctrineDbalDataLoader
	{
		$dataLoader = new DoctrineDbalDataLoader($this->smalldb, Post::class, $this->db);
		$dataLoader->setOnQueryCallback(function(QueryBuilder $q) {
			$this->queryCount++;
		});
		return $dataLoader;
	}


	public function getDataSourceQueryCount(): int
	{
		return $this->queryCount;
	}


	public function ref($id): Post
	{
		/** @var Post $ref */
		$ref = $this->getPostDataLoader()->ref($id);
		return $ref;
	}


	public function findBySlug(string $slug): ?Post
	{
		// TODO: Create a proper query object, which returns hydrated ReferenceInterface.

		$q = $this->getPostDataLoader()->createQueryBuilder();
		$q->where('slug = :slug');
		$q->setMaxResults(1);

		$q->setParameter('slug', $slug);
		$stmt = $q->execute();

		$this->queryCount++;

		/** @var Post|null $post */
		$post = $this->getPostDataLoader()->fetch($stmt);
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

		$q = $this->getPostDataLoader()->createQueryBuilder();
		$q->where('true');
		$q->orderBy('published_at', 'DESC');
		$q->addOrderBy('id', 'DESC');
		$q->setFirstResult($pageOffset);
		$q->setMaxResults($pageSize);
		$stmt = $q->execute();

		$this->queryCount++;

		$posts = $this->getPostDataLoader()->fetchAll($stmt);
		return $posts;
	}


	public function findAll(): iterable
	{
		$q = $this->getPostDataLoader()->createQueryBuilder();
		$q->where('true');
		$q->orderBy('published_at', 'DESC');
		$q->addOrderBy('id', 'DESC');
		$stmt = $q->execute();

		$this->queryCount++;

		$posts = $this->getPostDataLoader()->fetchAll($stmt);
		return $posts;
	}

}

