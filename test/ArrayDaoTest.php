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

use PHPUnit\Framework\TestCase;
use Smalldb\StateMachine\Test\Database\ArrayDao;


class ArrayDaoTest extends TestCase
{

	public function testCrudOperations()
	{
		$dao = new ArrayDao();

		$idAlice = $dao->create(['name' => 'Alice', 'foo' => 0]);
		$idBob = $dao->create(['name' => 'Bob', 'foo' => 0]);
		$idCarol = $dao->create(['name' => 'Carol', 'foo' => 0]);

		$this->assertTrue($dao->exists($idAlice));
		$this->assertTrue($dao->exists($idBob));
		$this->assertTrue($dao->exists($idCarol));

		$this->assertNotEquals($idAlice, $idBob, 'Alice and Bob have the same ID.');
		$this->assertNotEquals($idBob, $idCarol, 'Bob and Carol have the same ID.');

		$dao->update($idAlice, ['foo' => 1]);

		$newAliceData = $dao->read($idAlice);
		$this->assertEquals(['name' => 'Alice', 'foo' => 1], $newAliceData);

		$dao->delete($idBob);
		$this->assertNotTrue($dao->exists($idBob));

		$dao->delete($idAlice);
		$this->assertNotTrue($dao->exists($idAlice));
	}


	public function testReadFail()
	{
		$dao = new ArrayDao();
		$idAlice = $dao->create(['name' => 'Alice', 'foo' => 0]);

		$this->assertTrue($dao->exists($idAlice));
		$this->assertNotTrue($dao->exists($idAlice + 1));

		$this->expectException(\InvalidArgumentException::class);
		$dao->read($idAlice + 1);
	}


	public function testUpdateFail()
	{
		$dao = new ArrayDao();
		$idAlice = $dao->create(['name' => 'Alice', 'foo' => 0]);

		$this->assertTrue($dao->exists($idAlice));
		$this->assertNotTrue($dao->exists($idAlice + 1));

		$this->expectException(\InvalidArgumentException::class);
		$dao->update($idAlice + 1, ['foo' => 1]);
	}

	public function testDeleteFail()
	{
		$dao = new ArrayDao();
		$idAlice = $dao->create(['name' => 'Alice', 'foo' => 0]);

		$this->assertTrue($dao->exists($idAlice));
		$this->assertNotTrue($dao->exists($idAlice + 1));

		$this->expectException(\InvalidArgumentException::class);
		$dao->delete($idAlice + 1);
	}

}
