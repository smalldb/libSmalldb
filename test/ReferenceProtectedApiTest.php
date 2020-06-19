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

use Smalldb\StateMachine\Provider\SmalldbProviderInterface;
use Smalldb\StateMachine\ReferenceDataSource\ReferenceDataSourceInterface;
use Smalldb\StateMachine\ReferenceProtectedAPI;
use Smalldb\StateMachine\ReferenceTrait;
use Smalldb\StateMachine\Smalldb;


class ReferenceProtectedApiTest extends TestCase
{

	public function testReferenceTraitConsistency()
	{
		$refImplTrait = new \ReflectionClass(ReferenceTrait::class);
		$refApiTrait = new \ReflectionClass(ReferenceProtectedAPI::class);

		foreach ($refApiTrait->getMethods() as $apiMethod) {
			$implMethod = $refImplTrait->getMethod($apiMethod->getName());

			$this->assertTrue($apiMethod->isAbstract());
			$this->assertFalse($implMethod->isAbstract());
			$this->assertEquals($this->reflectionToArray($apiMethod), $this->reflectionToArray($implMethod));
		}

		$this->assertEmpty($refApiTrait->getProperties());
		$this->assertEmpty($refApiTrait->getConstants());
	}


	private function reflectionToArray(\ReflectionMethod $method)
	{
		$returnType = $method->getReturnType();
		return [
			'name' => $method->getName(),
			'isProtected' => $method->isProtected(),
			'return' => [
				'type' => $returnType->getName(),
				'allowsNull' => $returnType->allowsNull(),
			],
			'parameters' => array_map(function(\ReflectionParameter $param) {
				$type = $param->getType();
				return [
					'name' => $param->getName(),
					'type' => $type ? $type->getName() : null,
					'allowsNull' => $type ? $type->allowsNull() : null,
					'default' => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
				];
			}, $method->getParameters()),
		];
	}


	public function testReflectionAPI()
	{
		$smalldb = $this->createMock(Smalldb::class);
		$provider = $this->createMock(SmalldbProviderInterface::class);
		$datasource = $this->createMock(ReferenceDataSourceInterface::class);

		$c = new class($smalldb, $provider, $datasource, null) {
			use ReferenceProtectedAPI;
			use ReferenceTrait;

			public function getProtectedStuff()
			{
				return [
					$this->getSmalldb(),
					$this->getMachineProvider(),
					$this->getDataSource(),
				];
			}
		};

		[$retSmalldb, $retProvider, $retDatasource] = $c->getProtectedStuff();
		$this->assertSame($smalldb, $retSmalldb);
		$this->assertSame($provider, $retProvider);
		$this->assertSame($datasource, $retDatasource);
	}

}
