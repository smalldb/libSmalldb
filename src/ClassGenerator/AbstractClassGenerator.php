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

namespace Smalldb\StateMachine\ClassGenerator;


abstract class AbstractClassGenerator
{
	// TODO: Remove this class, do not pass SmalldbClassGenerator to generators;
	//      return proper class representation instead.

	private SmalldbClassGenerator $classGenerator;


	public function __construct(SmalldbClassGenerator $classGenerator)
	{
		$this->classGenerator = $classGenerator;
	}


	protected function getClassGenerator(): SmalldbClassGenerator
	{
		return $this->classGenerator;
	}

}
