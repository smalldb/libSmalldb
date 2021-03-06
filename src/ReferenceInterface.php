<?php declare(strict_types = 1);
/*
 * Copyright (c) 2012-2019, Josef Kufner  <josef@kufner.cz>
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

namespace Smalldb\StateMachine;

use Smalldb\StateMachine\Annotation\State;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Transition\TransitionEvent;


interface ReferenceInterface extends MachineIdentifierInterface
{
	/**
	 * @State
	 */
	const NOT_EXISTS = "";

	/**
	 * Invalidate cached data.
	 *
	 * TODO: Remove invalidateCache() and implement proper locking before invoking a transition.
	 *
	 * @internal
	 * @deprecated
	 */
	public function invalidateCache(): void;

	/**
	 * Read state machine state
	 */
	public function getState(): string;

	/**
	 * Get state machine definition
	 */
	public function getDefinition(): StateMachineDefinition;

	/**
	 * Return true when the transition is available.
	 */
	public function isTransitionAllowed(string $transitionName): bool;

	/**
	 * Invoke transition of the state machine.
	 */
	public function invokeTransition(string $transitionName, ...$args): TransitionEvent;

	/**
	 * Return value of the given property.
	 */
	public function get(string $propertyName);

}
