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

namespace Smalldb\StateMachine\Test\Example\SupervisorProcess;

use Smalldb\StateMachine\Annotation\StateMachine;
use Smalldb\StateMachine\DtoExtension\Annotation\WrapDTO;
use Smalldb\StateMachine\GraphMLExtension\Annotation\IncludeGraphML;
use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\Test\Example\SupervisorProcess\SupervisorProcessData\SupervisorProcessData;
use Smalldb\StateMachine\Test\Example\SupervisorProcess\SupervisorProcessData\SupervisorProcessDataImmutable;


/**
 * @StateMachine("supervisor-process")
 * @IncludeGraphML("SupervisorProcess.graphml")
 * @WrapDTO(SupervisorProcessDataImmutable::class)
 */
abstract class SupervisorProcess implements ReferenceInterface, SupervisorProcessData
{
	// TODO
}
