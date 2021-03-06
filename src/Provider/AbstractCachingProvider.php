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
use Smalldb\StateMachine\InvalidArgumentException;
use Smalldb\StateMachine\SmalldbDefinitionBagInterface;
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
	private ?string $machineType = null;
	private string $referenceClass;
	protected ?SmalldbDefinitionBagInterface $definitionBag = null;
	protected ?StateMachineDefinition $definition = null;
	protected ?TransitionDecorator $transitionsDecorator = null;
	protected ?SmalldbRepositoryInterface $repository = null;


	public function setReferenceClass(string $referenceClass)
	{
		if (!class_exists($referenceClass)) {
			throw new InvalidArgumentException("Reference class does not exist: $referenceClass");
		}
		$this->referenceClass = $referenceClass;
		return $this;
	}


	public function getReferenceClass(): string
	{
		return $this->referenceClass;
	}


	/**
	 * @return $this
	 */
	final public function setDefinition(?StateMachineDefinition $definition)
	{
		$this->definition = $definition;
		return $this;
	}


	/**
	 * @return $this
	 */
	final public function setDefinitionBag(SmalldbDefinitionBagInterface $definitionBag)
	{
		$this->definitionBag = $definitionBag;
		return $this;
	}


	/**
	 * @return $this
	 */
	final public function setMachineType(string $machineType)
	{
		$this->machineType = $machineType;
		return $this;
	}


	final public function getMachineType(): string
	{
		return $this->machineType ?? ($this->machineType = $this->getDefinition()->getMachineType());
	}


	final public function getDefinition(): StateMachineDefinition
	{
		return $this->definition
			?? ($this->definition = ($this->machineType !== null && $this->definitionBag !== null
					? $this->definitionBag->getDefinition($this->machineType)
					: $this->provideDefinition()));
	}


	abstract protected function provideDefinition(): StateMachineDefinition;


	/**
	 * @return $this
	 */
	final public function setTransitionsDecorator(?TransitionDecorator $transitionsDecorator)
	{
		$this->transitionsDecorator = $transitionsDecorator;
		return $this;
	}


	final public function getTransitionsDecorator(): TransitionDecorator
	{
		return $this->transitionsDecorator ?? ($this->transitionsDecorator = $this->provideTransitionsImplementation());
	}


	abstract protected function provideTransitionsImplementation(): TransitionDecorator;


	/**
	 * @return $this
	 */
	final public function setRepository(?SmalldbRepositoryInterface $repository)
	{
		$this->repository = $repository;
		return $this;
	}


	final public function getRepository(): SmalldbRepositoryInterface
	{
		return $this->repository ?? ($this->repository = $this->provideRepository());
	}


	abstract protected function provideRepository(): SmalldbRepositoryInterface;

}
