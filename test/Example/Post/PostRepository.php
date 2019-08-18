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


	public function __construct(Smalldb $smalldb, SymfonyDemoDatabase $pdo)
	{
		$this->smalldb = $smalldb;
		$this->pdo = $pdo;
	}


	/**
	 * Return the state of the refered state machine.
	 *
	 * @throws UnsupportedReferenceException
	 */
	public function getState(ReferenceInterface $ref): string
	{
		$id = $ref->getId();
		$stmt = $this->pdo->prepare("SELECT COUNT(id) FROM $this->table WHERE id = :id");
		$stmt->execute(['id' => $id]);
		$exists = intval($stmt->fetchColumn(0));
		$stmt->closeCursor();
		return $exists === 0 ? '' : 'Exists';
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
	 * Create a reference to a state machine identified by $id.
	 *
	 * @return ReferenceInterface
	 */
	public function ref($id): Post
	{
		if (!$this->machineProvider) {
			$this->machineProvider = $this->smalldb->getMachineProvider(Post::class);
			$this->refClass = $this->machineProvider->getReferenceClass();
		}

		/** @var ReferenceInterface $ref */
		$ref = new $this->refClass($id);
		$ref->smalldbConnect($this->smalldb, $this->machineProvider);
		return $ref;
	}


	protected function fetchReference(PDOStatement $stmt): ?Post
	{
		if (!$this->machineProvider) {
			$this->machineProvider = $this->smalldb->getMachineProvider(Post::class);
			$this->refClass = $this->machineProvider->getReferenceClass();
		}

		/** @var Post $ref */
		$ref = $stmt->fetchObject($this->refClass);
		if ($ref) {
			$ref->smalldbConnect($this->smalldb, $this->machineProvider);
			return $ref;
		} else {
			return null;
		}
	}

}

