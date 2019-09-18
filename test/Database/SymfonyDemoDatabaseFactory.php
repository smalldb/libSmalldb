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

namespace Smalldb\StateMachine\Test\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\DriverManager;


class SymfonyDemoDatabaseFactory
{

	/**
	 * @throws DBALException
	 */
	public function connect(): Connection
	{
		$connectionParams = array(
			'memory' => true,
			'driver' => 'pdo_sqlite',
		);
		$conn = DriverManager::getConnection($connectionParams);
		$this->importDatabase($conn);
		return $conn;
	}


	private function importDatabase(Connection $conn): void
	{
		$sqlFile = __DIR__ . "/../resources/symfony_demo_database.sqlite.sql";
		if (!file_exists($sqlFile)) {
			throw new \RuntimeException("Database SQL file does not exist: $sqlFile");
		}

		$sqlQueries = file_get_contents($sqlFile);
		if ($sqlQueries === false) {
			throw new \RuntimeException("Failed to read database SQL file: $sqlFile");
		}
		try {
			$conn->exec($sqlQueries);
		}
		catch (DBALException $ex) {
			throw new \RuntimeException("Failed to import database: " . $ex->getMessage(), 0, $ex);
		}
	}

}
