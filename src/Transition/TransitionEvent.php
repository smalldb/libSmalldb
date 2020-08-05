<?php declare(strict_types = 1);
/*
 * Copyright (c) 2019-2020, Josef Kufner  <josef@kufner.cz>
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

namespace Smalldb\StateMachine\Transition;

use Smalldb\StateMachine\ReferenceInterface;


class TransitionEvent
{
	private ReferenceInterface $ref;
	private string $transitionName;
	private array $transitionArgs;
	private bool $hasNewId = false;

	/** @var mixed */
	private $newId = null;

	/** @var mixed */
	private $returnValue = null;


	public function __construct(ReferenceInterface $ref, string $transitionName, array $transitionArgs)
	{
		$this->ref = $ref;
		$this->transitionName = $transitionName;
		$this->transitionArgs = $transitionArgs;
	}


	/**
	 * Reference to a subject state machine.
	 */
	public function getRef(): ReferenceInterface
	{
		return $this->ref;
	}


	/**
	 * Name of the transition.
	 */
	public function getTransitionName(): string
	{
		return $this->transitionName;
	}


	/**
	 * Transition arguments.
	 */
	public function getTransitionArgs(): array
	{
		return $this->transitionArgs;
	}


	/**
	 * ID of the state machine has changed, this is the new ID.
	 */
	public function setNewId($newId): void
	{
		$this->hasNewId = true;
		$this->newId = $newId;
	}


	/**
	 * Returns true if there is a new ID.
	 */
	public function hasNewId(): bool
	{
		return $this->hasNewId;
	}


	/**
	 * Get the new ID. The new ID may be null.
	 */
	public function getNewId()
	{
		return $this->newId;
	}


	/**
	 * @return mixed
	 */
	public function getReturnValue()
	{
		return $this->returnValue;
	}


	/**
	 * @param mixed $returnValue
	 */
	public function setReturnValue($returnValue): void
	{
		$this->returnValue = $returnValue;
	}

}
