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

use PDO;
use PDOStatement;
use Smalldb\StateMachine\Provider\SmalldbProviderInterface;
use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\SmalldbRepositoryInterface;
use Smalldb\StateMachine\Test\Database\SymfonyDemoDatabase;
use Smalldb\StateMachine\Test\Example\Tag\Tag;


class PostRepository implements SmalldbRepositoryInterface
{
	/** @var Smalldb */
	private $smalldb;

	/** @var SmalldbProviderInterface */
	private $machineProvider = null;

	/** @var string */
	private $refClass;

	/** @var PDO */
	private $pdo;
	private $table = 'symfony_demo_post';

	/** @var PostDataSource */
	private $postDataSource;

	private $queryCount = 0;


	public function __construct(Smalldb $smalldb, SymfonyDemoDatabase $pdo)
	{
		$this->smalldb = $smalldb;
		$this->pdo = $pdo;
		$this->postDataSource = $this->createDataSource();
	}


	private function createDataSource($preloadedDataSet = null): PostDataSource
	{
		return new PostDataSource($this->pdo, $this->table, $preloadedDataSet,
			function($rowCount) { $this->queryCount++; });
	}


	public function getDataSourceQueryCount(): int
	{
		return $this->queryCount;
	}


	/**
	 * Create a reference to a state machine identified by $id.
	 *
	 * @return ReferenceInterface
	 */
	public function ref($id): Post
	{
		$provider = $this->smalldb->getMachineProvider(Post::class);
		$refClass = $provider->getReferenceClass();
		$ref = new $refClass($this->smalldb, $provider, $this->postDataSource, $id);
		return $ref;
	}


	/**
	 * @return Post[]
	 */
	public function findLatest(int $page = 0, ?Tag $tag = null): array
	{
		assert($page >= 0);

		$pageSize = 25;
		$pageOffset = $page * $pageSize;

		if (!$this->machineProvider) {
			$this->machineProvider = $this->smalldb->getMachineProvider(Post::class);
			$this->refClass = $this->machineProvider->getReferenceClass();
		}

		$stmt = $this->pdo->prepare("
			SELECT id, author_id as authorId, title, slug, summary, content, published_at as publishedAt
			FROM $this->table
			ORDER BY published_at DESC, id DESC
			LIMIT :pageSize OFFSET :pageOffset
		");
		$stmt->execute(['pageSize' => $pageSize, 'pageOffset' => $pageOffset]);
		$this->queryCount++;

		$posts = $this->fetchAllReferences($stmt);
		return $posts;
	}


	/**
	 * @return Post[]
	 */
	protected function fetchAllReferences(PDOStatement $stmt): array
	{
		$posts = [];
		while (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
			$post = new $this->refClass($this->smalldb, $this->machineProvider, $this->postDataSource);
			($this->refClass)::hydrateFromArray($post, $row);
			$posts[] = $post;
		}
		return $posts;
	}


}

