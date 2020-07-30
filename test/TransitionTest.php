<?php declare(strict_types = 1);
/*
 * Copyright (c) 2020, Josef Kufner  <josef@kufner.cz>
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

use Smalldb\StateMachine\Definition\Builder\PreprocessorList;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilder;
use Smalldb\StateMachine\Definition\TransitionDefinition;
use Smalldb\StateMachine\Provider\AbstractCachingProvider;
use Smalldb\StateMachine\Provider\SmalldbProviderInterface;
use Smalldb\StateMachine\ReferenceDataSource\DummyDataSource;
use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\ReferenceTrait;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\Transition\MethodTransitionsDecorator;
use Smalldb\StateMachine\Transition\TransitionEvent;
use Smalldb\StateMachine\Transition\TransitionException;
use Smalldb\StateMachine\Transition\TransitionGuard;


class TransitionTest extends TestCase
{

	public function testTransitionDenied()
	{
		$builder = new StateMachineDefinitionBuilder(new PreprocessorList());
		$builder->setMachineType('foo');
		$builder->addState('Exists');
		$builder->addTransition('create', '', ['Exists']);
		$definition = $builder->build();

		$guard = $this->createMock(TransitionGuard::class);
		$guard->method('isTransitionAllowed')->willReturn(false);

		$tr = new class($guard) extends MethodTransitionsDecorator {
			public function create(TransitionEvent $transitionEvent, ReferenceInterface $ref): void
			{
				throw new \Exception('This should not happen.');
			}
		};

		$provider = $this->createMock(SmalldbProviderInterface::class);
		$provider->method('getTransitionsDecorator')->willReturn($tr);
		$provider->method('getDefinition')->willReturn($definition);

		$dataSource = new DummyDataSource();

		$ref = new class(new Smalldb, $provider, $dataSource) implements ReferenceInterface {
			use ReferenceTrait;

			public function getMachineId()
			{
				return 1;
			}

			public function getState(): string
			{
				return '';
			}


			public function invalidateCache(): void
			{
				// No-op.
			}

		};

		$this->expectException(TransitionException::class);
		$ref->invokeTransition('create');
	}


}
