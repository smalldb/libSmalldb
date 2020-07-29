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

namespace Smalldb\StateMachine\GraphMLExtension\Annotation;

use Smalldb\StateMachine\Annotation\AbstractIncludeAnnotation;
use Smalldb\StateMachine\Definition\AnnotationReader\ApplyToStateMachineBuilderInterface;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilder;
use Smalldb\StateMachine\GraphMLExtension\GraphMLDefinitionPreprocessorPass;


/**
 * Include GraphML state chart file
 *
 * @Annotation
 * @Target({"CLASS"})
 */
class IncludeGraphML extends AbstractIncludeAnnotation implements ApplyToStateMachineBuilderInterface
{
	/**
	 * @Required
	 */
	public string $fileName;
	public ?string $group = null;


	public function applyToBuilder(StateMachineDefinitionBuilder $builder): void
	{
		$builder->addPreprocessorPass(new GraphMLDefinitionPreprocessorPass($this->canonizeFileName($this->fileName), $this->group));
	}

}
