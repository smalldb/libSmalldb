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
use Smalldb\StateMachine\Provider\ReferenceFactoryInterface;
use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\SmalldbRepositoryInterface;
use Smalldb\StateMachine\Test\Database\SymfonyDemoDatabase;
use Smalldb\StateMachine\UnsupportedReferenceException;


class PostRepository implements SmalldbRepositoryInterface
{
	/** @var Smalldb */
	private $smalldb;

	/** @var PDO */
	private $pdo;
	private $table = 'symfony_demo_post';

	/** @var ReferenceFactoryInterface */
	private $refFactory;


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
		$stmt = $this->pdo->prepare("SELECT COUNT(id) FROM $this->table WHERE id = :id");
		[$id] = $ref->getId();
		$stmt->execute(['id' => $id]);
		$exists = $stmt->fetchColumn(0);
		$stmt->closeCursor();
		return $exists == 0 ? '' : 'Exists';
	}


	/**
	 * Load data for the state machine and set the state
	 *
	 * @throws UnsupportedReferenceException
	 */
	public function getData(ReferenceInterface $ref, ?string &$state)
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
		[$id] = $ref->getId();
		$stmt->execute(['id' => $id]);
		$data = $stmt->fetchObject(PostData::class);
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
	public function ref(...$id): Post
	{
		$ref = $this->getReferenceFactory()->createReference($this->smalldb, $id);
		return $ref;
	}

	private function getReferenceFactory(): ReferenceFactoryInterface
	{
		return $this->refFactory ?? ($this->refFactory = $this->smalldb->getMachineProvider(Post::class)->getReferenceFactory());
	}

}

