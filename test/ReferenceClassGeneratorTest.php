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
use Smalldb\StateMachine\CodeGenerator\SmalldbClassGenerator;
use Smalldb\StateMachine\Provider\LambdaProvider;
use Smalldb\StateMachine\CodeGenerator\ReferenceClassGenerator;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\SmalldbDefinitionBag;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItem;
use Smalldb\StateMachine\Test\TestTemplate\TestOutput;


class ReferenceClassGeneratorTest extends TestCase
{

	public function testReferenceGenerator()
	{
		$out = new TestOutput();
		$namespace = 'Smalldb\\GeneratedCode';
		$scg = new SmalldbClassGenerator($namespace, $out->mkdir('generated'));

		$origClass = CrudItem::class;

		$definitionBag = new SmalldbDefinitionBag();
		$definition = $definitionBag->addFromAnnotatedClass($origClass);

		$generator = new ReferenceClassGenerator($scg);
		$newClass = $generator->generateReferenceClass($origClass, $definition);

		// Try to create a dummy null reference
		$smalldb = new Smalldb();
		$provider = new LambdaProvider();
		$newClassInstance = new $newClass($smalldb, $provider, null);

		// The new reference must implement the original class, so we can use the original for type hints.
		$this->assertInstanceOf($origClass, $newClassInstance);
	}

}
