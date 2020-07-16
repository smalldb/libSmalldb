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
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\SqlExtension\ReferenceDataSource\DataSource;
use Smalldb\StateMachine\SqlExtension\ReferenceDataSource\LogicException;
use Smalldb\StateMachine\SqlExtension\ReferenceDataSource\OutOfBoundsException;
use Smalldb\StateMachine\SqlExtension\ReferenceDataSource\ReferenceQueryBuilder;
use Smalldb\StateMachine\SqlExtension\ReferenceDataSource\UnsupportedQueryException;
use Smalldb\StateMachine\Test\Example\Post\Post;
use Smalldb\StateMachine\Test\SmalldbFactory\SymfonyDemoContainer;


class SqlPaginationTest extends TestCase
{
	private Connection $db;
	private Smalldb $smalldb;


	public function setUp(): void
	{
		parent::setUp();
		$container = (new SymfonyDemoContainer())->createContainer();
		$this->smalldb = $container->get(Smalldb::class);
		$this->db = $container->get(Connection::class);
	}


	public function testPagination()
	{
		$qb = $this->createQueryBuilder();

		// Check that there is enough posts
		$stmt = $this->db->query("SELECT COUNT(*) FROM symfony_demo_post");
		$postCount = $stmt->fetchColumn();
		$this->assertEquals(30, $postCount, "Not enough posts in symfony_demo_post.");
		$pageSize = 7;

		$result = $qb->select()
			->addSelectFromStatements()
			->execPaginateRef(1, $pageSize);

		$this->assertTrue($result->hasToPaginate());
		$this->assertEquals(1, $result->getCurrentPage());
		$this->assertEquals($postCount, $result->getResultCount());
		$this->assertFalse($result->hasPreviousPage());
		$this->assertTrue($result->hasNextPage());
		$this->assertEquals(2, $result->getNextPage());
		$this->assertEquals(1, $result->getPreviousPage());
		$this->assertEquals($pageSize, $result->getPageSize());
		$this->assertEquals(ceil($postCount / $pageSize), $result->getPageCount());
		$this->assertEquals(1, $result->getFirstPage());
		$this->assertEquals(5, $result->getLastPage());

		$result = $qb->select()
			->addSelectFromStatements()
			->execPaginateRef(3, $pageSize);

		$this->assertTrue($result->hasToPaginate());
		$this->assertEquals(3, $result->getCurrentPage());
		$this->assertEquals($postCount, $result->getResultCount());
		$this->assertTrue($result->hasPreviousPage());
		$this->assertTrue($result->hasNextPage());
		$this->assertEquals(4, $result->getNextPage());
		$this->assertEquals(2, $result->getPreviousPage());
		$this->assertEquals($pageSize, $result->getPageSize());
		$this->assertEquals(ceil($postCount / $pageSize), $result->getPageCount());
		$this->assertEquals(1, $result->getFirstPage());
		$this->assertEquals(5, $result->getLastPage());

		$result = $qb->select()
			->addSelectFromStatements()
			->execPaginateRef(5, $pageSize);

		$this->assertTrue($result->hasToPaginate());
		$this->assertEquals($postCount, $result->getResultCount());
		$this->assertEquals(5, $result->getCurrentPage());
		$this->assertTrue($result->hasPreviousPage());
		$this->assertFalse($result->hasNextPage());
		$this->assertEquals(5, $result->getNextPage());
		$this->assertEquals(4, $result->getPreviousPage());
		$this->assertEquals($pageSize, $result->getPageSize());
		$this->assertEquals(ceil($postCount / $pageSize), $result->getPageCount());
		$this->assertEquals(1, $result->getFirstPage());
		$this->assertEquals(5, $result->getLastPage());

	}


	public function testNegativePage()
	{
		$qb = $this->createQueryBuilder();

		$this->expectException(OutOfBoundsException::class);

		$qb->select()
			->addSelectFromStatements()
			->execPaginateRef(-2, 10);
	}


	public function testNegativePageSize()
	{
		$qb = $this->createQueryBuilder();

		$this->expectException(OutOfBoundsException::class);

		$qb->select()
			->addSelectFromStatements()
			->execPaginateRef(1, -10);
	}


	public function testGroupBy()
	{
		$qb = $this->createQueryBuilder();

		$this->expectException(UnsupportedQueryException::class);

		$qb->select()
			->addSelectFromStatements()
			->groupBy('user_id')
			->execPaginateRef(1, 10);
	}


	public function testHaving()
	{
		$qb = $this->createQueryBuilder();

		$this->expectException(UnsupportedQueryException::class);

		$qb->select()
			->addSelectFromStatements()
			->having('user_id > 5')
			->execPaginateRef(1, 10);
	}


	public function testUpdateQuery()
	{
		$qb = $this->createQueryBuilder();

		$this->expectException(LogicException::class);

		$qb->update('symfony_demo_post')
			->set('title', '"Foo"')
			->where('1 = 2')        // Do not change data
			->executeRef();
	}


	private function createQueryBuilder(): ReferenceQueryBuilder
	{
		$dataSource = new DataSource(null, $this->smalldb, $this->smalldb->getMachineProvider(Post::class), $this->db);
		return $dataSource->createQueryBuilder();
	}

}
