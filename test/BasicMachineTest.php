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
use Smalldb\StateMachine\ArrayMachine;
use Smalldb\StateMachine\Reference;

class BasicMachineTest extends TestCase
{

	public function testCrudMachine()
	{
		$this->markTestIncomplete();

		$reader = new AnnotationReader(CrudItemMachine::class);
		$definition = $reader->getStateMachineDefinition();
		$this->assertInstanceOf(StateMachineDefinition::class, $definition);
	}

	public function testMachine()
	{
		$this->markTestSkipped('Test obsolete.');

		// Initialize backend
		$smalldb = new \Smalldb\StateMachine\Smalldb();
		$smalldbBackend = new Smalldb\StateMachine\SimpleBackend();
		$smalldbBackend->initializeBackend([]);
		$smalldb->registerBackend($smalldbBackend);

		// Register machine type
		$article_json = json_decode(file_get_contents(dirname(__FILE__) . '/example/article.json'), TRUE);
		$smalldbBackend->registerMachineType('article', ArrayMachine::class, $article_json['state_machine']);

		// Get known machine types
		$knownTypes = [];
		foreach ($smalldb->getBackends() as $backend) {
			foreach ($backend->getKnownTypes() as $t) {
				$knownTypes[] = $t;
			}
		}
		$this->assertContains('article', $knownTypes);

		// Check the machine
		$machine = $smalldb->getMachine('article');
		$this->assertInstanceOf(ArrayMachine::class, $machine);

		// Get null ref
		$nullRef = $smalldb->nullRef('article');
		$this->assertInstanceOf(Reference::class, $nullRef);
		$this->assertEquals('', $nullRef->state);

		// Available actions for the null ref
		$availableActions = $nullRef->actions;
		$this->assertEquals(['create'], $availableActions);

		// Create machine instantion - invoke initial transition
		$ref = $nullRef->create();
		$this->assertEquals('writing', $ref->state);

		// Available actions for the new ref
		$this->assertEquals(['edit', 'submit'], $ref->actions);

	}

}
