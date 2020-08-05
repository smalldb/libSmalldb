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

namespace Smalldb\StateMachine\AccessControlExtension\Definition\StateMachine;

use Smalldb\StateMachine\AccessControlExtension\Definition\StateMachineEdgeProcessorTrait;
use Smalldb\StateMachine\AccessControlExtension\Definition\Transition\AccessPolicyExtension;
use Smalldb\StateMachine\AccessControlExtension\Predicate\ContainerAdapter;
use Smalldb\StateMachine\Definition\ExtensionInterface;
use Smalldb\StateMachine\Definition\Renderer\StateMachineEdgeProcessor;
use Smalldb\StateMachine\Definition\StateMachineGraph\StateMachineEdge;
use Smalldb\StateMachine\Utils\SimpleJsonSerializableTrait;


class AccessControlExtension implements ExtensionInterface, StateMachineEdgeProcessor
{
	use SimpleJsonSerializableTrait;
	use StateMachineEdgeProcessorTrait;


	/** @var AccessControlPolicy[] */
	private array $policies;
	private ?string $defaultPolicyName;


	public function __construct(array $policies, ?string $defaultPolicyName)
	{
		$this->policies = $policies;
		$this->defaultPolicyName = $defaultPolicyName;
	}


	public function getPolicies(): array
	{
		return $this->policies;
	}


	public function getPolicy(string $policyName): ?AccessControlPolicy
	{
		if (isset($this->policies[$policyName])) {
			return $this->policies[$policyName];
		} else {
			return null;
		}
	}


	public function getDefaultPolicyName(): ?string
	{
		return $this->defaultPolicyName;
	}


	public function compilePolicyPredicates(ContainerAdapter $container): array
	{
		$policyPredicates = [];
		foreach ($this->policies as $policyName => $policy) {
			$policyPredicates[$policy->getName()] = $policy->getPredicate()->compile($container);
		}
		return $policyPredicates;
	}


	public function processEdgeAttrs(StateMachineEdge $edge, array &$exportedEdge)
	{
		if (!$edge->getTransition()->hasExtension(AccessPolicyExtension::class)) {
			// AccessPolicy not defined on the edge => use the default.
			$defaultPolicy = $this->getPolicy($this->getDefaultPolicyName());
			$this->runEdgeProcessor($defaultPolicy, $edge, $exportedEdge);
		}
	}

}
