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

namespace Smalldb\StateMachine\AccessControlExtension;

use Smalldb\StateMachine\AccessControlExtension\Definition\AccessControlExtension;
use Smalldb\StateMachine\Definition\TransitionDefinition;
use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\Transition\TransitionGuard;


class SimpleTransitionGuard implements TransitionGuard
{

	public function isTransitionAllowed(ReferenceInterface $ref, TransitionDefinition $transition): bool
	{
		/** @var AccessControlExtension $acrExt */
		$acrExt = $transition->getExtension(AccessControlExtension::class);

		if (!$acrExt) {
			return $this->getDefaultAccess();
		}

		$rule = $acrExt->getAccessControlRule();

		return $this->isAccessAllowed($rule, $ref);
	}


	protected function getDefaultAccess(): bool
	{
		// No Access Control Extension implies no access control.
		return true;
	}


	public function isAccessAllowed(AccessControlRule $rule, ReferenceInterface $ref): bool
	{
		// TODO: Implement isAccessAllowed() method.
		return true;
	}

}
