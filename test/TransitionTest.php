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
use Smalldb\StateMachine\Definition\DefinitionError;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Definition\UndefinedTransitionException;
use Smalldb\StateMachine\Provider\SmalldbProviderInterface;
use Smalldb\StateMachine\ReferenceDataSource\DummyDataSource;
use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\ReferenceTrait;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\Transition\MethodTransitionsDecorator;
use Smalldb\StateMachine\Transition\TransitionAccessException;
use Smalldb\StateMachine\Transition\TransitionDecorator;
use Smalldb\StateMachine\Transition\TransitionEvent;
use Smalldb\StateMachine\Transition\TransitionException;
use Smalldb\StateMachine\Transition\TransitionGuard;


class TransitionTest extends TestCase
{

	public function testTransition()
	{
		$definition = $this->createBuilder()->build();

		$ref = $this->createReference($definition, true, 123);

		$this->assertTrue($ref->isTransitionAllowed('create'));

		$ev = $ref->invokeTransition('create');
		$this->assertEquals(123, $ev->getReturnValue());
	}


	public function testTransitionDenied()
	{
		$definition = $this->createBuilder()->build();

		$ref = $this->createReference($definition, false, null);

		$this->assertFalse($ref->isTransitionAllowed('create'));

		$this->expectException(TransitionAccessException::class);
		$ref->invokeTransition('create');
	}


	public function testInvalidTransitionButValidAction()
	{
		$builder = $this->createBuilder();
		$builder->addAction('foo');
		$definition = $builder->build();

		$ref = $this->createReference($definition, false, null);
		$this->assertFalse($ref->isTransitionAllowed('foo'));

		$this->expectException(UndefinedTransitionException::class);
		$ref->invokeTransition('foo');
	}


	public function testTransitionWithErrors()
	{
		$builder = $this->createBuilder();
		$builder->addError(new DefinitionError('Something is wrong.'));
		$definition = $builder->build();

		$ref = $this->createReference($definition, true, null);

		$this->expectException(TransitionException::class);
		$ref->invokeTransition('create');
	}


	/**
	 * @return StateMachineDefinitionBuilder
	 */
	private function createBuilder(): StateMachineDefinitionBuilder
	{
		$builder = new StateMachineDefinitionBuilder(new PreprocessorList());
		$builder->setMachineType('foo');
		$builder->addTransition('create', '', ['']);
		return $builder;
	}


	private function createReference(StateMachineDefinition $definition, bool $allow, ?int $id): ReferenceInterface
	{
		$tr = $this->createTransitionDecorator($allow);

		$provider = $this->createMock(SmalldbProviderInterface::class);
		$provider->method('getTransitionsDecorator')->willReturn($tr);
		$provider->method('getDefinition')->willReturn($definition);

		$dataSource = new DummyDataSource();

		$ref = new class(new Smalldb, $provider, $dataSource) implements ReferenceInterface {
			use ReferenceTrait;

			public ?int $id = null;

			public function getMachineId()
			{
				return $this->id;
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
		$ref->id = $id;
		return $ref;
	}


	private function createTransitionDecorator(bool $allow): TransitionDecorator
	{
		$guard = $this->createMock(TransitionGuard::class);
		$guard->method('isTransitionAllowed')->willReturn($allow);

		return new class($guard) extends MethodTransitionsDecorator {
			public function create(TransitionEvent $transitionEvent, ReferenceInterface $ref): ?int
			{
				$id = $ref->getMachineId();
				if ($id === null) {
					throw new \Exception('This should not happen.');
				} else {
					return $id;
				}
			}
		};
	}

}
