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

use PDO;
use Smalldb\StateMachine\Test\TestTemplate\TestOutput;

/**
 * Demo database connection which creates ephemeral copy of the database.
 */
class SymfonyDemoDatabase extends \PDO
{
	/**
	 * @var string
	 */
	private $dbFileName;


	public function __construct(TestOutput $output, string $dbFileName = 'symfony_demo_database.sqlite')
	{
		// FIXME: This expects tests to run only one at once
		$this->dbFileName = $output->outputPath($output->resource($dbFileName));

		parent::__construct('sqlite:' . $this->dbFileName, null, null, [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		]);
	}


	public function __destruct()
	{
		// FIXME: Allow parallel runs of the tests
		if (file_exists($this->dbFileName)) {
			unlink($this->dbFileName);
		}
	}


	/**
	 * @return string
	 */
	public function getDbFileName(): string
	{
		return $this->dbFileName;
	}

}
