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

	protected $preloadedDataSet;

	/** @var callable */
	protected $onQueryCallback;


	public function __construct(PDO $pdo, string $table, $preloadedDataSet = null, ?callable $onQueryCallback = null)
	{
		$this->pdo = $pdo;
		$this->table = $table;
		$this->preloadedDataSet = $preloadedDataSet;
		$this->onQueryCallback = $onQueryCallback ?? function($rowCount) {};
	}


	/**
	 * Return the state of the refered state machine.
	 */
	public function getState($id): string
	{
		if (isset($this->preloadedDataSet[$id])) {
			return 'Exists';
		} else {
			$stmt = $this->pdo->prepare("SELECT COUNT(id) FROM $this->table WHERE id = :id");
			$stmt->execute(['id' => $id]);
			($this->onQueryCallback)($stmt->rowCount());
			$exists = intval($stmt->fetchColumn(0));
			$stmt->closeCursor();
			return $exists === 0 ? '' : 'Exists';
		}
	}


	/**
	 * Load data for the state machine and set the state
	 */
	public function loadData($id, ?string &$state): array
	{
		if (isset($this->preloadedDataSet[$id])) {
			$data = $this->preloadedDataSet[$id];
		} else {
			$stmt = $this->pdo->prepare("
				SELECT id, author_id as authorId, title, slug, summary, content, published_at as publishedAt
				FROM $this->table
				WHERE id = :id
				LIMIT 1
			");
			$stmt->execute(['id' => $id]);
			($this->onQueryCallback)($stmt->rowCount());
			$data = $stmt->fetch(PDO::FETCH_ASSOC);
		}

		if ($data) {
			$state = 'Exists';
			return $data;
		} else {
			$state = '';
			throw new \LogicException('Cannot load data in the Not Exists state.');
		}
	}


	public function invalidateCache($id = null)
	{
		if ($id === null) {
			$this->preloadedDataSet = null;
		} else {
			unset($this->preloadedDataSet[$id]);
		}
	}

	/**
	 * @param null $preloadedDataSet
	 */
	public function setPreloadedDataSet(array $preloadedDataSet): void
	{
		$this->preloadedDataSet = $preloadedDataSet;
	}

}
