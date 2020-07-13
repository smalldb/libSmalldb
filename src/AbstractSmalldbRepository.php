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

namespace Smalldb\StateMachine;

use Smalldb\StateMachine\Provider\SmalldbProviderInterface;


abstract class AbstractSmalldbRepository implements SmalldbRepositoryInterface
{
	/** @var string */
	protected const REF_CLASS = null;

	protected Smalldb $smalldb;
	private ?SmalldbProviderInterface $machineProvider = null;
	private ?string $refClass = null;


	public function __construct(Smalldb $smalldb)
	{
		if (static::REF_CLASS === null) {
			throw new \LogicException("Reference class not configured in " . __CLASS__ . "::REF_CLASS constant.");
		}
		$this->smalldb = $smalldb;
	}


	public function getMachineProvider(): SmalldbProviderInterface
	{
		return $this->machineProvider ?? ($this->machineProvider = $this->smalldb->getMachineProvider(static::REF_CLASS));
	}


	public function getReferenceClass(): string
	{
		return $this->refClass ?? ($this->refClass = $this->getMachineProvider()->getReferenceClass());
	}

}
