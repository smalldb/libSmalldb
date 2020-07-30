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
use Smalldb\StateMachine\AccessControlExtension\Predicate\ContainerAdapter;
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

	/** @var \Closure[][][] */
	private array $transitionPredicates;
	/** @var PredicateCompiled[][][] */
	private array $transitionPredicateCache = [];
	private bool $defaultAllow;


	/**
	 * @param \Closure[][][] $transitionPredicates
	 */
	public function __construct(array $transitionPredicates = [], bool $defaultAllow = true)
	{
		$this->transitionPredicates = $transitionPredicates;
		$this->defaultAllow = $defaultAllow;
	}


	public static function compileTransitionPredicatesSymfony(SmalldbDefinitionBagInterface $definitionBag, ContainerBuilder $container)
	{
		$containerAdapter = new SymfonyContainerAdapter($container);
		return static::compileTransitionPredicates($definitionBag, $containerAdapter);
	}


	public static function compileTransitionPredicates(SmalldbDefinitionBagInterface $definitionBag, ContainerAdapter $containerAdapter)
	{
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
					if ($tr->hasExtension(AccessPolicyExtension::class)) {
						/** @var AccessPolicyExtension $policyExt */
						$policyExt = $tr->getExtension(AccessPolicyExtension::class);
						$policyName = $policyExt->getPolicyName();
					} else {
						$policyName = $defaultPolicyName;
					}

					if (empty($predicates[$policyName])) {
						throw new InvalidArgumentException("Access policy not found: $policyName");
					}
					$guardPredicates[$machineType][$sourceState][$transitionName]
						= $containerAdapter->closureWrap($predicates[$policyName]);
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

			// Check cached predicate
			if (isset($this->transitionPredicateCache[$machineType][$sourceState][$transitionName])) {
				return $this->transitionPredicateCache[$machineType][$sourceState][$transitionName]->evaluate($ref);
			}

			// Load predicate
			$predicate = $this->getTransitionPredicate($machineType, $sourceState, $transitionName);
			if ($predicate !== null) {
				$this->transitionPredicateCache[$machineType][$sourceState][$transitionName] = $predicate;
				return $predicate->evaluate($ref);
			}

			// No predicate
			return false;
		} else {
			return $this->defaultAllow;
		}
	}


	public function getTransitionPredicates(): array
	{
		return $this->transitionPredicates;
	}


	private function getTransitionPredicate(string $machineType, string $sourceState, string $transitionName): ?PredicateCompiled
	{
		$p = $this->transitionPredicates[$machineType][$sourceState][$transitionName] ?? null;
		if ($p instanceof PredicateCompiled) {
			return $p;
		} else if (is_callable($p)) {
			return $p();
		} else {
			return null;
		}
	}

}
