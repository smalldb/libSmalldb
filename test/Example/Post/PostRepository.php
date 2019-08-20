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


	public function __construct(Smalldb $smalldb, SymfonyDemoDatabase $pdo)
	{
		$this->smalldb = $smalldb;
		$this->pdo = $pdo;
		$this->postDataSource = new PostDataSource($pdo, $this->table);
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
	 * Load data for the state machine and set the state
	 *
	 * @throws UnsupportedReferenceException
	 */
	public function loadData(ReferenceInterface $ref, ?string &$state)
	{
		if (!($ref instanceof Post)) {
			throw new UnsupportedReferenceException('Reference not supported: ' . get_class($ref));
		}

		$stmt = $this->pdo->prepare("
			SELECT id, author_id as authorId, title, slug, summary, content, published_at as publishedAt
			FROM $this->table
			WHERE id = :id
			LIMIT 1
		");
		$id = $ref->getId();
		$stmt->execute(['id' => $id]);
		$data = $stmt->fetchObject(PostDataImmutable::class);
		if ($data === false) {
			$state = '';
			throw new \LogicException('Cannot load data in the Not Exists state.');
		} else {
			$state = 'Exists';
			return $data;
		}
	}


	/**
	 * @returns Post[]
	 */
	public function findLatest(int $page = 0, ?Tag $tag = null): array
	{
		assert($page >= 0);

		$pageSize = 10;
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

		$posts = [];
		while (($post = $this->fetchReference($stmt))) {
			$posts[$post->getId()] = $post;
		}
		return $posts;
	}


	public $fetchMode = 1;

	protected function fetchReference(PDOStatement $stmt): ?Post
	{
		// Test various implementations
		switch ($this->fetchMode) {
			case 1: return $this->fetchReference1($stmt);
			case 2: return $this->fetchReference2($stmt);
			default: throw new \LogicException('Invalid PostRepository::$fetchMode.');
		}
	}

	private function fetchReference1(PDOStatement $stmt): ?Post
	{
		$ref = $stmt->fetchObject($this->refClass, [$this->smalldb, $this->machineProvider, $this->postDataSource]);
		return $ref ?: null;
	}

	private function fetchReference2(PDOStatement $stmt): ?Post
	{
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ? new $this->refClass($this->smalldb, $this->machineProvider, $this->postDataSource, null, $row) : null;
	}

}

