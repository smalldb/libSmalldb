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

use Smalldb\StateMachine\InvalidArgumentException;
use Smalldb\StateMachine\Provider\LambdaProvider;
use Smalldb\StateMachine\Provider\SmalldbProviderInterface;
use Smalldb\StateMachine\Smalldb;


class BasicSmalldbTest extends TestCase
{

	private function createProvider(string $machineType): SmalldbProviderInterface
	{
		$provider = new LambdaProvider();
		$provider->setMachineType($machineType);
		return $provider;
	}


	public function testRegisterMachine()
	{
		$smalldb = new Smalldb();
		$fooProvider = $this->createProvider('foo');
		$smalldb->registerMachineType($fooProvider);
		$smalldb->registerMachineType($this->createProvider('bar'));
		$smalldb->registerMachineType($this->createProvider('baz'));

		$registeredTypes = $smalldb->getMachineTypes();
		$this->assertContainsEquals('foo', $registeredTypes);

		$this->assertEquals($fooProvider, $smalldb->getMachineProvider('foo'));
	}


	public function testMissingMachine()
	{
		$smalldb = new Smalldb();
		$smalldb->registerMachineType($this->createProvider('foo1'), ['foo']);
		$smalldb->registerMachineType($this->createProvider('foo2'));
		$smalldb->registerMachineType($this->createProvider('foo3'));

		$registeredTypes = $smalldb->getMachineTypes();
		$this->assertNotContainsEquals('bar', $registeredTypes);

		$this->expectException(InvalidArgumentException::class);
		$smalldb->getMachineProvider('bar');
	}


	public function testDuplicateMachine()
	{
		$smalldb = new Smalldb();
		$smalldb->registerMachineType($this->createProvider('foo'));
		$this->expectException(InvalidArgumentException::class);
		$smalldb->registerMachineType($this->createProvider('foo'));
	}


	public function testDuplicateAliasedMachine()
	{
		$smalldb = new Smalldb();
		$smalldb->registerMachineType($this->createProvider('foo1'), ['foo']);
		$this->expectException(InvalidArgumentException::class);
		$smalldb->registerMachineType($this->createProvider('foo'));
	}


	public function testDuplicateMachineAlias()
	{
		$smalldb = new Smalldb();
		$smalldb->registerMachineType($this->createProvider('foo1'), ['foo']);
		$this->expectException(InvalidArgumentException::class);
		$smalldb->registerMachineType($this->createProvider('foo2'), ['foo']);
	}

}
