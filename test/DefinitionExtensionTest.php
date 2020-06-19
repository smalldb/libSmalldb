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

use Smalldb\StateMachine\Definition\ActionDefinition;
use Smalldb\StateMachine\Definition\ExtensionInterface;
use Smalldb\StateMachine\Definition\ExtensibleDefinition;
use Smalldb\StateMachine\Definition\PropertyDefinition;
use Smalldb\StateMachine\Definition\StateDefinition;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Definition\TransitionDefinition;
use Smalldb\StateMachine\Definition\UndefinedExtensionException;
use Smalldb\StateMachine\Definition\InvalidExtensionException;


class DefinitionExtensionTest extends TestCase
{

	public function definitionFactoryProvider()
	{
		yield StateMachineDefinition::class => [function($extensions) {
			return new StateMachineDefinition('Foo', time(), [], [], [], [], [], null, null, null, $extensions);
		}];

		yield StateDefinition::class => [function($extensions) {
			return new StateDefinition('Foo', null, $extensions);
		}];

		yield TransitionDefinition::class => [function($extensions) {
			return new TransitionDefinition('Foo', new StateDefinition('Foo'), [new StateDefinition('Bar')], null, $extensions);
		}];

		yield ActionDefinition::class => [function($extensions) {
			return new ActionDefinition('Foo', [], $extensions);
		}];

		yield PropertyDefinition::class => [function($extensions) {
			return new PropertyDefinition('foo', 'int', false, $extensions);
		}];
	}


	/**
	 * @dataProvider definitionFactoryProvider
	 */
	public function testDefinitionExtension(callable $definitionFactory)
	{
		$extMock = $this->createMock(ExtensionInterface::class);
		$extClassName = get_class($extMock);

		$extensions = [
			$extClassName => $extMock,
		];

		/** @var ExtensibleDefinition $definition */
		$definition = $definitionFactory($extensions);
		$this->assertInstanceOf(ExtensibleDefinition::class, $definition);

		$this->assertTrue($definition->hasExtension($extClassName));
		$this->assertSame($extMock, $definition->getExtension($extClassName));

		$this->assertFalse($definition->hasExtension('Foo'));

		$this->expectException(UndefinedExtensionException::class);
		$definition->getExtension('Foo');
	}


	/**
	 * @dataProvider definitionFactoryProvider
	 */
	public function testInvalidListOfExtensions(callable $definitionFactory)
	{
		$extMock = $this->createMock(ExtensionInterface::class);

		$extensions = [$extMock];  // Array not indexed properly.

		$this->expectException(\InvalidArgumentException::class);
		$definitionFactory($extensions);
	}


	/**
	 * @dataProvider definitionFactoryProvider
	 */
	public function testInvalidDefinitionExtension(callable $definitionFactory)
	{
		$extMock = $this->createMock(ExtensionInterface::class);

		$extensions = [
			'Foo' => $extMock,
		];

		/** @var ExtensibleDefinition $definition */
		$definition = $definitionFactory($extensions);
		$this->assertInstanceOf(ExtensibleDefinition::class, $definition);

		$this->assertTrue($definition->hasExtension('Foo'));

		$this->expectException(InvalidExtensionException::class);
		$definition->getExtension('Foo');
	}

}
