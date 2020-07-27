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

use Smalldb\StateMachine\AccessControlExtension\Definition\AccessControlPolicy;
use Smalldb\StateMachine\InvalidArgumentException;


class AccessPolicyRegistry
{

	/** @var AccessControlPolicy[] $policies */
	protected array $policies = [];


	/**
	 * @param AccessControlPolicy[] $policies
	 */
	public function __construct(array $policies = [])
	{
		foreach ($policies as $p) {
			$this->addPolicy($p);
		}
	}


	public function addPolicy(AccessControlPolicy $policy)
	{
		$name = $policy->getName();
		if (isset($this->policies[$name])) {
			throw new InvalidArgumentException("Duplicate global access policy: $name");
		}
		$this->policies[$name] = $policy;
	}


	public function getPolicy(string $policyName): ?AccessControlPolicy
	{
		if (isset($this->policies[$policyName])) {
			return $this->policies[$policyName];
		} else {
			return null;
		}
	}


	/**
	 * @return AccessControlPolicy[]
	 */
	public function getPolicies(): array
	{
		return $this->policies;
	}

}
