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

namespace Smalldb\StateMachine\DoctrineExtension\Annotation;

use Smalldb\StateMachine\Definition\AnnotationReader\ApplyToStateMachineBuilderInterface;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilder;
use Smalldb\StateMachine\DoctrineExtension\Definition\DoctrineDefinitionPreprocessorPass;


/**
 * State machine provides properties of the given Doctrine entity.
 *
 * @Annotation
 * @Target({"CLASS"})
 */
class DoctrineEntity implements ApplyToStateMachineBuilderInterface
{
	public string $className;


	public function applyToBuilder(StateMachineDefinitionBuilder $builder): void
	{
		$builder->addPreprocessorPass(new DoctrineDefinitionPreprocessorPass($this->className));
	}

}
