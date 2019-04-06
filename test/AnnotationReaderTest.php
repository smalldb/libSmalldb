<?php
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
use Smalldb\StateMachine\AnnotationReader;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Test\Example\StateMachine\CrudItemMachine;


class AnnotationReaderTest extends TestCase
{

	public function testCrudItem()
	{
		$reader = new AnnotationReader(CrudItemMachine::class);
		$definition = $reader->getStateMachineDefinition();
		$this->assertInstanceOf(StateMachineDefinition::class, $definition);
	}

}
