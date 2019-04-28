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

use Smalldb\StateMachine\Definition\TransitionDefinition;


class MethodTransitionsDecorator extends AbstractTransitionDecorator implements TransitionDecorator
{

	/**
	 * Invoke the transition.
	 */
	protected function doInvokeTransition(TransitionEvent $transitionEvent, TransitionDefinition $transitionDefinition): void
	{
		$method = $transitionEvent->getTransitionName();
		if (!method_exists($this, $method)) {
			throw new MissingTransitionImplementationException('Missing transition implementation - undefined method: ' . __CLASS__ . '::' . $method . '()');
		}

		$result = $this->$method($transitionEvent, $transitionEvent->getRef(), ...$transitionEvent->getTransitionArgs());
		$transitionEvent->setReturnValue($result);
	}

	/*
	protected function transition(TransitionEvent $transitionEvent, ReferenceInterface $ref, ...$args)
	{
		...
	}
	*/

}
