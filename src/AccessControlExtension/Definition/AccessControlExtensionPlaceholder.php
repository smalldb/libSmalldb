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

namespace Smalldb\StateMachine\AccessControlExtension\Definition;


use Smalldb\StateMachine\Definition\Builder\ExtensionPlaceholderInterface;
use Smalldb\StateMachine\Definition\ExtensionInterface;
use Smalldb\StateMachine\InvalidArgumentException;


class AccessControlExtensionPlaceholder implements ExtensionPlaceholderInterface
{
	/** @var AccessControlPolicy[] */
	public array $policies = [];

	public ?string $defaultPolicyName = null;


	public function __construct()
	{
	}


	public function addPolicy(AccessControlPolicy $policy): void
	{
		$name = $policy->getName();
		if (isset($this->policies[$name])) {
			throw new InvalidArgumentException("Duplicate access policy: $name");
		}
		$this->policies[$name] = $policy;
	}


	public function buildExtension(): ?AccessControlExtension
	{
		return empty($this->policies) && $this->defaultPolicyName === null ? null
			: new AccessControlExtension($this->policies, $this->defaultPolicyName);
	}

}
