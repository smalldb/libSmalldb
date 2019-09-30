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
use Smalldb\StateMachine\ReferenceDataSource\ReferenceDataSourceInterface;


/**
 * Protected API of a Reference objects.
 *
 * Use this trait in the abstract state machine definition classes to gain
 * access to the protected methods implemented by Smalldb.
 *
 * Do not use ReferenceTrait directly; it may damage your definitions.
 */
trait ReferenceProtectedAPI
{
	abstract protected function getSmalldb(): Smalldb;
	abstract protected function getMachineProvider(): SmalldbProviderInterface;
	abstract protected function getDataSource(): ReferenceDataSourceInterface;
}
