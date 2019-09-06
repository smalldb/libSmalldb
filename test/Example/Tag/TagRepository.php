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

namespace Smalldb\StateMachine\Test\Example\Tag;

use PDO;
use PDOStatement;
use Smalldb\StateMachine\Provider\SmalldbProviderInterface;
use Smalldb\StateMachine\ReferenceDataSource\PdoDataLoader;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\SmalldbRepositoryInterface;
use Smalldb\StateMachine\Test\Database\SymfonyDemoDatabase;


class TagRepository implements SmalldbRepositoryInterface
{
	/** @var Smalldb */
	private $smalldb;

	/** @var SmalldbProviderInterface */
	private $machineProvider = null;

	/** @var string */
	private $refClass = null;

	/** @var PdoDataLoader */
	private $dataLoader = null;

	/** @var PDO */
	private $pdo;


	public function __construct(Smalldb $smalldb, SymfonyDemoDatabase $pdo)
	{
		$this->smalldb = $smalldb;
		$this->pdo = $pdo;
	}


	public function getMachineProvider(): SmalldbProviderInterface
	{
		return $this->machineProvider ?? ($this->machineProvider = $this->smalldb->getMachineProvider(Tag::class));
	}


	public function getReferenceClass(): string
	{
		return $this->refClass ?? ($this->refClass = $this->getMachineProvider()->getReferenceClass());
	}


	private function getDataLoader(): PdoDataLoader
	{
		return $this->dataLoader ?? ($this->dataLoader = $this->createDataLoader());
	}


	// TODO: Load this from the definition
	private $table = 'symfony_demo_tag';
	private $selectColumns = 'id, name';

	private function createDataLoader($preloadedDataSet = null): PdoDataLoader
	{
		$dataLoader = new PdoDataLoader($this->smalldb, $this->getMachineProvider());
		$dataLoader->setStateSelectPreparedStatement($this->pdo->prepare("
			SELECT 'Exists' AS state
			FROM $this->table
			WHERE id = :id
			LIMIT 1
		"));
		$dataLoader->setLoadDataPreparedStatement($this->pdo->prepare("
			SELECT $this->selectColumns
			FROM $this->table
			WHERE id = :id
			LIMIT 1
		"));
		$dataLoader->setOnQueryCallback(function (PDOStatement $stmt) {
			//$this->queryCount++;
		});
		return $dataLoader;
	}


	public function ref($id): Tag
	{
		/** @var Tag $ref */
		$ref = $this->getDataLoader()->ref($id);
		return $ref;
	}

}
