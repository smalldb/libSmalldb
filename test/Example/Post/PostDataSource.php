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
use Smalldb\StateMachine\ReferenceDataSource\ReferenceDataSourceInterface;
use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\UnsupportedReferenceException;


class PostDataSource implements ReferenceDataSourceInterface
{
	/** @var PDO */
	protected $pdo;

	/** @var string */
	protected $table;


	public function __construct(PDO $pdo, string $table)
	{
		$this->pdo = $pdo;
		$this->table = $table;
	}


	/**
	 * Return the state of the refered state machine.
	 */
	public function getState($id): string
	{
		$stmt = $this->pdo->prepare("SELECT COUNT(id) FROM $this->table WHERE id = :id");
		$stmt->execute(['id' => $id]);
		$exists = intval($stmt->fetchColumn(0));
		$stmt->closeCursor();
		return $exists === 0 ? '' : 'Exists';
	}


	/**
	 * Load data for the state machine and set the state
	 */
	public function loadData($id, ?string &$state)
	{
		$stmt = $this->pdo->prepare("
			SELECT id, author_id as authorId, title, slug, summary, content, published_at as publishedAt
			FROM $this->table
			WHERE id = :id
			LIMIT 1
		");
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

}
