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

namespace Smalldb\StateMachine\Annotation;

use Smalldb\StateMachine\Definition\AnnotationReader\ApplyToStateMachineBuilderInterface;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilder;

/**
 * Link to a repository class which should the state machine reference use.
 *
 * @Annotation
 * @Target({"CLASS"})
 */
class UseRepository implements ApplyToStateMachineBuilderInterface
{
	public string $repositoryClass;

	public function applyToBuilder(StateMachineDefinitionBuilder $builder): void
	{
		$builder->setRepositoryClass($this->repositoryClass);
	}

}
