<?php declare(strict_types = 1);
/*
 * Copyright (c) 2020, Josef Kufner  <josef@kufner.cz>
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

namespace Smalldb\StateMachine\Test;

use Doctrine\DBAL\Connection;
use Psr\Container\ContainerInterface;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\Test\Database\SqlQueryCounter;
use Smalldb\StateMachine\Test\SmalldbFactory\SymfonyDemoContainer;


class TestCaseWithDemoContainer extends TestCase implements ContainerInterface
{
	protected Smalldb $smalldb;
	protected Connection $db;
	private ContainerInterface $container;


	public function setUp(): void
	{
		$containerFactory = new SymfonyDemoContainer();
		$this->container = $containerFactory->createContainer();
		$this->smalldb = $this->get(Smalldb::class);
		$this->db = $this->get(Connection::class);

		// Check that the database is not empty
		$stmt = $this->db->query("SELECT COUNT(*) FROM symfony_demo_post");
		$this->assertGreaterThan(0, $stmt->fetchColumn());

		$this->resetQueryCount();
	}


	public function get($service)
	{
		return $this->container->get($service);
	}


	public function has($service): bool
	{
		return $this->container->has($service);
	}


	protected function getQueryCount(): int
	{
		$logger = $this->db->getConfiguration()->getSQLLogger();
		if ($logger instanceof SqlQueryCounter) {
			return $logger->getQueryCount();
		} else {
			throw new \LogicException("SQL Logger is not a " . SqlQueryCounter::class);
		}
	}


	protected function resetQueryCount(): void
	{
		$logger = $this->db->getConfiguration()->getSQLLogger();
		if ($logger instanceof SqlQueryCounter) {
			$logger->resetQueryCount();
		} else {
			throw new \LogicException("SQL Logger is not a " . SqlQueryCounter::class);
		}
	}


	protected function assertQueryCountEquals(int $expected, string $message = '')
	{
		$actual = $this->getQueryCount();
		$this->assertEquals($expected, $actual, $message !== '' ? $message
				: "Unexpected query count: $actual (should be $expected)");
	}


	protected function assertQueryCountLessThanOrEqual(int $expected, string $message = '')
	{
		$actual = $this->getQueryCount();
		$this->assertLessThanOrEqual($expected, $actual, $message !== '' ? $message
			: "Unexpected query count: $actual (should be $expected or less)");
	}

}
