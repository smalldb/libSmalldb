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


namespace Smalldb\StateMachine\Test;

use Smalldb\StateMachine\Test\Database\ArrayDao;
use Smalldb\StateMachine\Test\Database\ArrayDaoTables;


class ArrayDaoTablesTest extends TestCase
{

	public function testInit()
	{
		$db = new ArrayDaoTables();
		$foo1 = $db->createTable("foo");
		$bar1 = $db->createTable("bar");

		$foo2 = $db->table("foo");
		$bar2 = $db->table("bar");

		$this->assertInstanceOf(ArrayDao::class, $foo1);
		$this->assertInstanceOf(ArrayDao::class, $bar1);
		$this->assertTrue($foo1 === $foo2);
		$this->assertTrue($bar1 === $bar2);
		$this->assertTrue($foo1 !== $bar1);
		$this->assertTrue($foo2 !== $bar2);
	}


	public function testDuplicateCreate()
	{
		$db = new ArrayDaoTables();
		$db->createTable("foo");

		$this->expectException(\InvalidArgumentException::class);
		$db->createTable("foo");
	}

	public function testObtainUndefined()
	{
		$db = new ArrayDaoTables();
		$db->createTable("foo");

		$this->expectException(\InvalidArgumentException::class);
		$db->table("bar");
	}

}
