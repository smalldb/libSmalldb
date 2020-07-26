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

use InvalidArgumentException;
use Smalldb\StateMachine\Definition\Builder\ActionPlaceholder;
use Smalldb\StateMachine\Definition\Builder\ExtensiblePlaceholder;
use Smalldb\StateMachine\Definition\Builder\ExtensionPlaceholderInterface;
use Smalldb\StateMachine\Definition\Builder\InvalidExtensionPlaceholderException;
use Smalldb\StateMachine\Definition\Builder\PropertyPlaceholder;
use Smalldb\StateMachine\Definition\Builder\StatePlaceholder;
use Smalldb\StateMachine\Definition\Builder\TransitionPlaceholder;
use Smalldb\StateMachine\Definition\ExtensionInterface;
use Smalldb\StateMachine\Definition\UndefinedExtensionException;
use Smalldb\StateMachine\Definition\InvalidExtensionException;


class DefinitionExtensionPlaceholderTest extends TestCase
{

	public function definitionFactoryProvider()
	{

		yield StatePlaceholder::class => [function($extensions) {
			return new StatePlaceholder('Foo', $extensions);
		}];

		yield TransitionPlaceholder::class => [function($extensions) {
			return new TransitionPlaceholder('foo', 'Foo', ['Bar'], $extensions);
		}];

		yield ActionPlaceholder::class => [function($extensions) {
			return new ActionPlaceholder('foo', $extensions);
		}];

		yield PropertyPlaceholder::class => [function($extensions) {
			return new PropertyPlaceholder('foo', 'int', false, $extensions);
		}];
	}


	/**
	 * @dataProvider definitionFactoryProvider
	 */
	public function testPlaceholderExtension(callable $placeholderFactory)
	{
		$extMock = $this->createMock(ExtensionPlaceholderInterface::class);
		$extClassName = get_class($extMock);

		$extensions = [
			$extClassName => $extMock,
		];

		/** @var ExtensiblePlaceholder $placeholder */
		$placeholder = $placeholderFactory($extensions);
		$this->assertInstanceOf(ExtensiblePlaceholder::class, $placeholder);

		$this->assertTrue($placeholder->hasExtensionPlaceholder($extClassName));

		$this->assertSame($extMock, $placeholder->getExtensionPlaceholder($extClassName));

		$this->expectException(\TypeError::class);
		$placeholder->getExtensionPlaceholder(\ArrayObject::class);
	}


	/**
	 * @dataProvider definitionFactoryProvider
	 */
	public function testInvalidListOfExtensions(callable $placeholderFactory)
	{
		$extMock = $this->createMock(ExtensionPlaceholderInterface::class);

		$extensions = [$extMock];  // Array not indexed properly.

		$this->expectException(InvalidArgumentException::class);
		$placeholderFactory($extensions);
	}


	/**
	 * @dataProvider definitionFactoryProvider
	 */
	public function testInvalidPlaceholderExtension(callable $placeholderFactory)
	{
		$extMock = $this->createMock(ExtensionPlaceholderInterface::class);

		$extensions = [
			'Foo' => $extMock,
		];

		/** @var ExtensiblePlaceholder $placeholder */
		$placeholder = $placeholderFactory($extensions);
		$this->assertInstanceOf(ExtensiblePlaceholder::class, $placeholder);

		$this->assertTrue($placeholder->hasExtensionPlaceholder('Foo'));

		$this->expectException(InvalidExtensionPlaceholderException::class);
		$placeholder->getExtensionPlaceholder('Foo');
	}

}
