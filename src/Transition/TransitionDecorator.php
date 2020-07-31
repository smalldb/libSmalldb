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

namespace Smalldb\StateMachine\Transition;

use Smalldb\StateMachine\DebugLoggerInterface;
use Smalldb\StateMachine\Definition\TransitionDefinition;
use Smalldb\StateMachine\ReferenceInterface;


interface TransitionDecorator
{
	/**
	 * TODO: Move $debugLogger somewhere else?
	 */
	public function invokeTransition(TransitionEvent $transitionEvent, ?DebugLoggerInterface $debugLogger = null): TransitionEvent;

	public function isTransitionAllowed(ReferenceInterface $ref, TransitionDefinition $transition): bool;

}
