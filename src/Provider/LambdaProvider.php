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
use Smalldb\StateMachine\SmalldbDefinitionBagInterface;
use Smalldb\StateMachine\SmalldbRepositoryInterface;
use Smalldb\StateMachine\Transition\TransitionDecorator;


class LambdaProvider extends AbstractCachingProvider implements SmalldbProviderInterface
{
	/** @var callable */
	private $definitionFactory;

	/** @var callable */
	private $definitionBagFactory;

	/** @var callable */
	private $transitionsDecoratorFactory;

	/** @var callable */
	private $repositoryFactory;


	public static function createWithDefinitionBag( string $machineType, string $referenceClass,
		SmalldbDefinitionBagInterface $definitionBag,
		?callable $transitionsDecoratorFactory, ?callable $repositoryFactory): self
	{
		$self = new self($machineType, $referenceClass);
		$self->setDefinitionBag($definitionBag);
		$self->transitionsDecoratorFactory = $transitionsDecoratorFactory;
		$self->repositoryFactory = $repositoryFactory;
		return $self;
	}


	public static function createWithDefinitionBagFactory(string $machineType, string $referenceClass,
		?callable $definitionBagFactory,
		?callable $transitionsDecoratorFactory, ?callable $repositoryFactory): self
	{
		$self = new self($machineType, $referenceClass);
		$self->definitionBagFactory = $definitionBagFactory;
		$self->transitionsDecoratorFactory = $transitionsDecoratorFactory;
		$self->repositoryFactory = $repositoryFactory;
		return $self;
	}


	public function __construct(?string $machineType = null, ?string $referenceClass = null,
		?callable $definitionFactory = null,
		?callable $transitionsDecoratorFactory = null,
		?callable $repositoryFactory = null
	) {
		if ($machineType !== null) {
			$this->setMachineType($machineType);
		}

		if ($referenceClass !== null) {
			$this->setReferenceClass($referenceClass);
		}

		$this->definitionFactory = $definitionFactory;
		$this->transitionsDecoratorFactory = $transitionsDecoratorFactory;
		$this->repositoryFactory = $repositoryFactory;
	}


	public function setDefinitionFactory(callable $definitionFactory): self
	{
		$this->definitionFactory = $definitionFactory;
		return $this;
	}


	public function setRepositoryFactory(callable $repositoryFactory): self
	{
		$this->repositoryFactory = $repositoryFactory;
		return $this;
	}


	public function setTransitionsDecoratorFactory(callable $transitionsDecoratorFactory): self
	{
		$this->transitionsDecoratorFactory = $transitionsDecoratorFactory;
		return $this;
	}


	protected function provideDefinition(): StateMachineDefinition
	{
		if (isset($this->definitionFactory)) {
			return ($this->definitionFactory)();
		} else if (isset($this->definitionBagFactory)) {
			$this->setDefinitionBag(($this->definitionBagFactory)());
			return parent::getDefinition();
		} else {
			throw new \LogicException("Definition not set.");
		}
	}


	protected function provideTransitionsImplementation(): TransitionDecorator
	{
		if (isset($this->transitionsDecoratorFactory)) {
			return ($this->transitionsDecoratorFactory)();
		} else {
			throw new \LogicException("Transitions implementation not set.");
		}
	}


	public function provideRepository(): SmalldbRepositoryInterface
	{
		if (isset($this->repositoryFactory)) {
			return ($this->repositoryFactory)();
		} else {
			throw new \LogicException("Repository not set.");
		}
	}

}
