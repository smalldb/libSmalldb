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
use Smalldb\StateMachine\ReferenceInterface;
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
abstract class AbstractCachingProvider implements SmalldbProviderInterface
{
	/** @var string|null */
	private $referenceClass;

	/** @var StateMachineDefinition */
	protected $definition;

	/** @var TransitionDecorator */
	protected $transitionsDecorator;

	/** @var SmalldbRepositoryInterface */
	protected $repository;


	public function getReferenceFactory(): callable
	{
		if (isset($this->referenceClass)) {
			return function(Smalldb $smalldb, ...$id): ReferenceInterface {
				return new $this->referenceClass($smalldb, $this, ...$id);
			};
		} else {
			throw new \LogicException("Reference class not set.");
		}
	}


	/**
	 * @return $this
	 */
	public function setReferenceClass(string $referenceClass)
	{
		$this->referenceClass = $referenceClass;
		return $this;
	}


	public function getReferenceClass(): string
	{
		return $this->referenceClass;
	}


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
