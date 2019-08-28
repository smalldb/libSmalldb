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
use Smalldb\StateMachine\UnsupportedReferenceException;


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


	public $fetchMode = 5;
	const FETCH_MODES = [3, 4, '4c', '4d', '4b', 5, 6];

	/**
	 * @return Post[]
	 */
	protected function fetchAllReferences(PDOStatement $stmt): array
	{
		// Test various implementations
		switch ($this->fetchMode) {
			case 3: return $this->fetchAllReferences3($stmt);
			case 4: return $this->fetchAllReferences4($stmt);
			case '4c': return $this->fetchAllReferences4c($stmt);
			case '4b': return $this->fetchAllReferences4b($stmt);
			case '4d': return $this->fetchAllReferences4d($stmt);
			case 5: return $this->fetchAllReferences5($stmt);
			case 6: return $this->fetchAllReferences6($stmt);
			default: throw new \LogicException('Invalid PostRepository::$fetchMode.');  // @codeCoverageIgnore
		}
	}


	/**
	 * @return Post[]
	 */
	private function fetchAllReferences3(PDOStatement $stmt): array
	{
		$hydrator = function(array $src): void {
			$this->id = $src['id'];
			$this->title = $src['title'];
			$this->slug = $src['slug'];
			$this->summary = $src['summary'];
			$this->content = $src['content'];
			$this->publishedAt = $src['publishedAt'];
			$this->authorId = $src['authorId'];
			$this->dataLoaded = true;
		};

		$posts = [];
		while (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
			$post = new $this->refClass($this->smalldb, $this->machineProvider, $this->postDataSource);
			$hydrator->call($post, $row);
			$posts[] = $post;
		}
		return $posts;
	}

	/**
	 * @return Post[]
	 */
	private function fetchAllReferences4(PDOStatement $stmt): array
	{
		$posts = [];
		while (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
			$post = new $this->refClass($this->smalldb, $this->machineProvider, $this->postDataSource);
			($this->refClass)::hydrateFromArray($post, $row);
			$posts[] = $post;
		}
		return $posts;
	}


	/**
	 * @return Post[]
	 */
	private function fetchAllReferences4c(PDOStatement $stmt): array
	{
		$posts = [];
		while (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
			$post = new $this->refClass($this->smalldb, $this->machineProvider, $this->postDataSource);
			($this->refClass)::hydrateFromArrayIfSet($post, $row);
			$posts[] = $post;
		}
		return $posts;
	}


	/**
	 * @return Post[]
	 */
	private function fetchAllReferences4d(PDOStatement $stmt): array
	{
		$posts = [];
		while (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
			$post = new $this->refClass($this->smalldb, $this->machineProvider, $this->postDataSource);
			($this->refClass)::hydrateFromArrayOrNull($post, $row);
			$posts[] = $post;
		}
		return $posts;
	}


	/**
	 * @return Post[]
	 */
	private function fetchAllReferences4b(PDOStatement $stmt): array
	{
		$posts = [];
		while (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
			$post = new $this->refClass($this->smalldb, $this->machineProvider, $this->postDataSource);
			($this->refClass)::hydrateClosureFromArray($post, $row);
			$posts[] = $post;
		}
		return $posts;
	}


	/**
	 * @return Post[]
	 */
	private function fetchAllReferences5(PDOStatement $stmt): array
	{
		$hydrator = ($this->refClass)::createHydrator($this->smalldb, $this->machineProvider, $this->postDataSource);

		$posts = $stmt->fetchAll(PDO::FETCH_FUNC, $hydrator);
		return $posts;
	}


	/**
	 * @return Post[]
	 */
	private function fetchAllReferences6(PDOStatement $stmt): array
	{
		$hydrator = ($this->refClass)::createHydrator($this->smalldb, $this->machineProvider, $this->postDataSource);

		$posts = [];
		while (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
			$posts[] = $hydrator(...array_values($row));
		}
		return $posts;
	}

}

