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
	private $refClass = null;

	/** @var PDO */
	private $pdo;
	private $table = 'symfony_demo_post';

	/** @var PostDataSource */
	private $postDataSource = null;

	private $queryCount = 0;

	const POST_SELECT_COLUMNS = '"Exists" as state, id, author_id as authorId, title, slug, summary, content, published_at as publishedAt';


	public function __construct(Smalldb $smalldb, SymfonyDemoDatabase $pdo)
	{
		$this->smalldb = $smalldb;
		$this->pdo = $pdo;
	}


	public function getMachineProvider(): SmalldbProviderInterface
	{
		return $this->machineProvider ?? ($this->machineProvider = $this->smalldb->getMachineProvider(Post::class));
	}


	public function getReferenceClass(): string
	{
		return $this->refClass ?? ($this->refClass = $this->getMachineProvider()->getReferenceClass());
	}


	private function getPostDataSource($preloadedDataSet = null): PostDataSource
	{
		return $this->postDataSource ?? ($this->postDataSource
			= new PostDataSource($this->pdo, $this->table, $preloadedDataSet,
				function($rowCount) { $this->queryCount++; }));
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
		$refClass = $this->getReferenceClass();
		$ref = new $refClass($this->smalldb, $this->getMachineProvider(), $this->getPostDataSource(), $id);
		return $ref;
	}


	public function findBySlug(string $slug): ?Post
	{
		$stmt = $this->pdo->prepare("
			SELECT " . static::POST_SELECT_COLUMNS . "
			FROM $this->table
			WHERE slug = :slug
			LIMIT 1
		");

		$stmt->execute(['slug' => $slug]);
		$this->queryCount++;

		return $this->fetchSingle($stmt);
	}


	/**
	 * @return Post[]
	 */
	public function findLatest(int $page = 0, ?Tag $tag = null): array
	{
		assert($page >= 0);

		$pageSize = 25;
		$pageOffset = $page * $pageSize;

		$stmt = $this->pdo->prepare("
			SELECT " . static::POST_SELECT_COLUMNS . "
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
		$machineProvider = $this->getMachineProvider();
		$refClass = $this->getReferenceClass();
		$postDataSource = $this->getPostDataSource();

		$posts = [];
		while (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
			$post = new $refClass($this->smalldb, $machineProvider, $postDataSource);
			($this->refClass)::hydrateFromArray($post, $row);
			$posts[] = $post;
		}
		return $posts;
	}


	protected function fetchSingle(PDOStatement $stmt): ?Post
	{
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($row === false) {
			return null;
		} else {
			$post = new $this->refClass($this->smalldb, $this->getMachineProvider(), $this->getPostDataSource());
			($this->refClass)::hydrateFromArray($post, $row);
			return $post;
		}
	}

}

