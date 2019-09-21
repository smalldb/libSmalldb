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

namespace Smalldb\StateMachine\SqlExtension\ReferenceDataSource;

use PDO;
use PDOStatement;
use Smalldb\StateMachine\Provider\SmalldbProviderInterface;
use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\Smalldb;


/**
 * Class PdoDataLoader
 *
 * @deprecated
 */
class PdoDataLoader extends PdoDataSource
{
	/** @var Smalldb */
	private $smalldb;

	/** @var SmalldbProviderInterface */
	private $machineProvider;

	/** @var string */
	private $refClass;


	public function __construct(Smalldb $smalldb, SmalldbProviderInterface $machineProvider)
	{
		parent::__construct();
		$this->smalldb = $smalldb;
		$this->machineProvider = $machineProvider;
		$this->refClass = $machineProvider->getReferenceClass();
	}


	public function ref($id): ReferenceInterface
	{
		return new $this->refClass($this->smalldb, $this->machineProvider, $this, $id);
	}


	public function fetch(PDOStatement $stmt): ?ReferenceInterface
	{
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($row === false) {
			return null;
		} else {
			$ref = new $this->refClass($this->smalldb, $this->machineProvider, $this);
			($this->refClass)::hydrateFromArray($ref, $row);
			return $ref;
		}
	}


	/**
	 * @return ReferenceInterface[]
	 */
	public function fetchAll(PDOStatement $stmt): array
	{
		$list = [];
		while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
			$ref = new $this->refClass($this->smalldb, $this->machineProvider, $this);
			($this->refClass)::hydrateFromArray($ref, $row);
			$list[] = $ref;
		}
		return $list;
	}


	/**
	 * @return iterable<ReferenceInterface>
	 *
	 * TODO: Return a proper collection which provides rowCount() and other useful features.
	 */
	public function fetchAllIter(PDOStatement $stmt): iterable
	{
		while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
			$ref = new $this->refClass($this->smalldb, $this->machineProvider, $this);
			($this->refClass)::hydrateFromArray($ref, $row);
			yield $ref;
		}
	}

}
