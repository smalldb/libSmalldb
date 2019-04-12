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
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilder;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Definition\Renderer\StateMachineRenderer;


class BpmnTest extends TestCase
{
	private $outputDir;

	public function setUp(): void
	{
		$this->outputDir = __DIR__ . '/output';
		if (!is_dir($this->outputDir)) {
			$outputDirCreated = mkdir($this->outputDir);
			$this->assertTrue($outputDirCreated, 'Failed to create output directory: ' . $this->outputDir);
		}
	}

	public function testBasicExport()
	{
		$htmlFilename = $this->outputDir . '/crud.html';

		$builder = new StateMachineDefinitionBuilder();
		$builder->setMachineType('crud-item');
		$builder->addState('Exists');
		$builder->addTransition('create', '', ['Exists']);
		$builder->addTransition('update', 'Exists', ['Exists']);
		$builder->addTransition('delete', 'Exists', ['']);
		$definition = $builder->build();
		$this->assertInstanceOf(StateMachineDefinition::class, $definition);
		
		$renderer = new StateMachineRenderer();
		$renderer->renderSimpleHtmlPage($definition, $htmlFilename);
		$this->assertFileExists($htmlFilename);
	}

}
