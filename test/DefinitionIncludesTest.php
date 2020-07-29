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

use Smalldb\StateMachine\BpmnExtension\Definition\BpmnDefinitionPreprocessor;
use Smalldb\StateMachine\BpmnExtension\Definition\BpmnDefinitionPreprocessorPass;
use Smalldb\StateMachine\Definition\AnnotationReader\AnnotationReader;
use Smalldb\StateMachine\Definition\Builder\PreprocessorPassException;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilder;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilderFactory;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\GraphMLExtension\GraphMLDefinitionPreprocessor;
use Smalldb\StateMachine\GraphMLExtension\GraphMLDefinitionPreprocessorPass;
use Smalldb\StateMachine\Test\Example\Bpmn\PizzaDelivery;
use Smalldb\StateMachine\Test\Example\SupervisorProcess\SupervisorProcess;


class DefinitionIncludesTest extends TestCase
{

	public function testPreprocessors()
	{
		$factory = new StateMachineDefinitionBuilderFactory();
		$factory->addPreprocessor(new BpmnDefinitionPreprocessor());
		$factory->addPreprocessor(new GraphMLDefinitionPreprocessor());

		$plist = $factory->getPreprocessorList();
		$this->assertTrue($plist->supports(new BpmnDefinitionPreprocessorPass('foo.bpmn', 'foo')));
		$this->assertTrue($plist->supports(new GraphMLDefinitionPreprocessorPass('foo.graphml')));
	}


	public function testInvalidPreprocessorPass()
	{
		$factory = new StateMachineDefinitionBuilderFactory();
		$factory->addPreprocessor(new BpmnDefinitionPreprocessor());

		$plist = $factory->getPreprocessorList();
		$pass = new GraphMLDefinitionPreprocessorPass('foo.graphml');
		$this->assertFalse($plist->supports($pass));

		$builder = new StateMachineDefinitionBuilder($plist);
		$builder->addPreprocessorPass($pass);

		$this->expectException(PreprocessorPassException::class);
		$builder->build();
	}


	public function testGraphML()
	{
		$reader = new AnnotationReader(StateMachineDefinitionBuilderFactory::createDefaultFactory());
		$definition = $reader->getStateMachineDefinition(SupervisorProcess::class);
		$this->assertInstanceOf(StateMachineDefinition::class, $definition);
		$this->assertNotEmpty($definition->getStates());
		$this->assertNotEmpty($definition->getTransitions());
	}


	public function testBPMN()
	{
		$reader = new AnnotationReader(StateMachineDefinitionBuilderFactory::createDefaultFactory());
		$definition = $reader->getStateMachineDefinition(PizzaDelivery::class);
		$this->assertInstanceOf(StateMachineDefinition::class, $definition);
		$this->assertNotEmpty($definition->getStates());
		$this->assertNotEmpty($definition->getTransitions());
	}

}
