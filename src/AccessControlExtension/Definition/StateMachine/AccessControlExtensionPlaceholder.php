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

namespace Smalldb\StateMachine\AccessControlExtension\Definition\StateMachine;

use Smalldb\StateMachine\Definition\Builder\ExtensionPlaceholderInterface;
use Smalldb\StateMachine\InvalidArgumentException;
use Smalldb\StateMachine\LogicException;


class AccessControlExtensionPlaceholder implements ExtensionPlaceholderInterface
{
	/** @var AccessControlPolicyPlaceholder[] */
	public array $policyPlaceholders = [];

	public ?string $defaultPolicyName = null;


	public function __construct()
	{
	}


	public function addPolicy(AccessControlPolicyPlaceholder $policy): void
	{
		if ($policy->name == '') {
			throw new InvalidArgumentException("Unnamed access policy.");
		}
		if (isset($this->policyPlaceholders[$policy->name])) {
			throw new InvalidArgumentException("Duplicate access policy: $policy->name");
		}
		$this->policyPlaceholders[$policy->name] = $policy;
	}


	public function buildExtension(): ?AccessControlExtension
	{
		$policies = [];
		foreach ($this->policyPlaceholders as $policyName => $policyPlaceholder) {
			if ($policyName !== $policyPlaceholder->name) {
				throw new LogicException("Access policy name has changed.");
			}
			$policy = $policyPlaceholder->buildAccessPolicy();
			$policies[$policy->getName()] = $policy;
		}

		return empty($policies) && $this->defaultPolicyName === null ? null
			: new AccessControlExtension($policies, $this->defaultPolicyName);
	}

}
