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

use Psr\Container\ContainerInterface;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\SmalldbRepositoryInterface;
use Smalldb\StateMachine\Transition\TransitionDecorator;


class ContainerStateMachineProvider extends AbstractCachingStateMachineProvider implements SmalldbStateMachineProviderInterface
{
	/** @var ContainerInterface */
	private $container;

	/** @var string|null */
	private $definitionId;

	/** @var string|null */
	private $transitionsImplementationId;

	/** @var string|null */
	private $repositoryId;


	public function __construct(ContainerInterface $container)
	{
		$this->container = $container;
	}


	protected function provideDefinition(): StateMachineDefinition
	{
		if (isset($this->definitionId)) {
			return $this->container->get($this->definitionId);
		} else {
			throw new \LogicException("Definition ID not set.");
		}
	}


	protected function provideTransitionsImplementation(): TransitionDecorator
	{
		if (isset($this->transitionsImplementationId)) {
			return $this->container->get($this->transitionsImplementationId);
		} else {
			throw new \LogicException("Transitions implementation ID not set.");
		}
	}


	protected function provideRepository(): SmalldbRepositoryInterface
	{
		if (isset($this->repositoryId)) {
			return $this->container->get($this->repositoryId);
		} else {
			throw new \LogicException("Repository ID not set.");
		}
	}



	public function setDefinitionId(string $definitionId): self
	{
		$this->definitionId = $definitionId;
		return $this;
	}


	public function setTransitionsImplementationId(string $transitionsImplementationId): self
	{
		$this->transitionsImplementationId = $transitionsImplementationId;
		return $this;
	}


	public function setRepositoryId(string $repositoryId): self
	{
		$this->repositoryId = $repositoryId;
		return $this;
	}

}
