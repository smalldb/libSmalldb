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

namespace Smalldb\StateMachine\AccessControlExtension\Definition\Transition;

use Smalldb\StateMachine\AccessControlExtension\Definition\StateMachine\AccessControlExtension;
use Smalldb\StateMachine\AccessControlExtension\Definition\StateMachineEdgeProcessorTrait;
use Smalldb\StateMachine\Definition\ExtensionInterface;
use Smalldb\StateMachine\Definition\Renderer\StateMachineEdgeProcessor;
use Smalldb\StateMachine\Definition\StateMachineGraph\StateMachineEdge;
use Smalldb\StateMachine\Utils\SimpleJsonSerializableTrait;


class AccessPolicyExtension implements ExtensionInterface, StateMachineEdgeProcessor
{
	use SimpleJsonSerializableTrait;
	use StateMachineEdgeProcessorTrait;

	private string $policyName;


	public function __construct(string $policyName)
	{
		$this->policyName = $policyName;
	}


	public function getPolicyName(): string
	{
		return $this->policyName;
	}


	public function processEdgeAttrs(StateMachineEdge $edge, array &$exportedEdge)
	{
		/** @var AccessControlExtension $acExt */
		if (($acExt = $edge->getStateMachine()->findExtension(AccessControlExtension::class))) {
			$policy = $acExt->getPolicy($this->getPolicyName());
			$this->runEdgeProcessor($policy, $edge, $exportedEdge);
		}
	}

}
