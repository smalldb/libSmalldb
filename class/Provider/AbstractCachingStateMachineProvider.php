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

namespace Smalldb\StateMachine\Provider;

use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Reference;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\SmalldbRepositoryInterface;
use Smalldb\StateMachine\Transition\TransitionDecorator;


/**
 * Class AbstractStateMachineProvider
 *
 * A simple caching provider with caching getters. Implement the provide* methods
 * to feed the respective getters. Each of the provide* methods will be called
 * only once.
 */
abstract class AbstractCachingStateMachineProvider implements SmalldbStateMachineProviderInterface
{
	/** @var callable */
	protected $referenceFactory;

	/** @var StateMachineDefinition */
	protected $definition;

	/** @var TransitionDecorator */
	protected $transitionsDecorator;

	/** @var SmalldbRepositoryInterface */
	protected $repository;


	final public function getReferenceFactory(): callable
	{
		return $this->referenceFactory ?? ($this->referenceFactory = $this->provideReferenceFactory());
	}

	abstract protected function provideReferenceFactory(): callable;


	final public function getDefinition(): StateMachineDefinition
	{
		return $this->definition ?? ($this->definition = $this->provideDefinition());
	}

	abstract protected function provideDefinition(): StateMachineDefinition;


	final public function getTransitionsDecorator(): TransitionDecorator
	{
		return $this->transitionsDecorator ?? ($this->transitionsDecorator = $this->provideTransitionsImplementation());
	}

	abstract protected function provideTransitionsImplementation(): TransitionDecorator;


	final public function getRepository(): SmalldbRepositoryInterface
	{
		return $this->repository ?? ($this->repository = $this->provideRepository());
	}

	abstract protected function provideRepository(): SmalldbRepositoryInterface;

}
