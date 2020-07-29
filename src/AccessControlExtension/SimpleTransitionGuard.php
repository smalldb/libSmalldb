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
use Smalldb\StateMachine\AccessControlExtension\Definition\AccessPolicyExtension;
use Smalldb\StateMachine\AccessControlExtension\Predicate\PredicateCompiled;
use Smalldb\StateMachine\AccessControlExtension\Predicate\SymfonyContainerAdapter;
use Smalldb\StateMachine\Definition\TransitionDefinition;
use Smalldb\StateMachine\InvalidArgumentException;
use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\SmalldbDefinitionBagInterface;
use Smalldb\StateMachine\Transition\TransitionGuard;
use Symfony\Component\DependencyInjection\ContainerBuilder;


class SimpleTransitionGuard implements TransitionGuard
{

	/** @var PredicateCompiled[][][] */
	private array $transitionPredicates;


	/**
	 * @param PredicateCompiled[][][] $transitionPredicates
	 */
	public function __construct(array $transitionPredicates = [])
	{
		$this->transitionPredicates = $transitionPredicates;
	}


	public static function compileTransitionPredicates(SmalldbDefinitionBagInterface $definitionBag, ContainerBuilder $container)
	{
		$containerAdapter = new SymfonyContainerAdapter($container);

		$guardPredicates = [];

		foreach ($definitionBag->getAllDefinitions() as $definition) {
			if ($definition->hasExtension(AccessControlExtension::class)) {
				/** @var AccessControlExtension $ext */
				$ext = $definition->getExtension(AccessControlExtension::class);
				$predicates = $ext->compilePolicyPredicates($containerAdapter);
				$defaultPolicyName = $ext->getDefaultPolicyName();

				$machineType = $definition->getMachineType();
				foreach ($definition->getTransitions() as $tr) {
					$transitionName = $tr->getName();
					$sourceState = $tr->getSourceState()->getName();
					/** @var AccessPolicyExtension $policyExt */
					$policyExt = $tr->getExtension(AccessPolicyExtension::class);
					$policyName = $policyExt ? $policyExt->getPolicyName() : $defaultPolicyName;

					if (empty($predicates[$policyName])) {
						throw new InvalidArgumentException("Access policy not found: $policyName");
					}
					$guardPredicates[$machineType][$sourceState][$transitionName] = $predicates[$policyName];
				}
			}
		}

		return $guardPredicates;
	}


	public function isTransitionAllowed(ReferenceInterface $ref, TransitionDefinition $transition): bool
	{
		return $this->isAccessAllowed($ref->getMachineType(), $transition->getName(), $ref);
	}


	public function isAccessAllowed(string $machineType, string $transitionName, ReferenceInterface $ref): bool
	{
		if (!empty($this->transitionPredicates[$machineType])) {
			$sourceState = $ref->getState();
			if (isset($this->transitionPredicates[$machineType][$sourceState][$transitionName])) {
				return $this->transitionPredicates[$machineType][$sourceState][$transitionName]->evaluate($ref);
			} else {
				return false;
			}
		} else {
			return true;
		}
	}


	public function getTransitionPredicates(): array
	{
		return $this->transitionPredicates;
	}

}
