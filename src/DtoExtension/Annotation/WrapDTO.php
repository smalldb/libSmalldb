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

namespace Smalldb\StateMachine\DtoExtension\Annotation;

use Smalldb\StateMachine\Definition\AnnotationReader\RecursiveAnnotationIncludeInterface;
use Smalldb\StateMachine\Definition\AnnotationReader\ApplyToStateMachineBuilderInterface;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilder;
use Smalldb\StateMachine\DtoExtension\Definition\DtoExtensionPlaceholder;


/**
 * @Annotation
 * @Target({"CLASS"})
 */
class WrapDTO implements ApplyToStateMachineBuilderInterface, RecursiveAnnotationIncludeInterface
{
	public string $dtoClassName;


	public function applyToBuilder(StateMachineDefinitionBuilder $builder): void
	{
		if (!class_exists($this->dtoClassName)) {
			throw new \InvalidArgumentException("WrapDTO requires a class name of existing class: " . $this->dtoClassName);
		}

		/** @var DtoExtensionPlaceholder $extPlaceholder */
		$extPlaceholder = $builder->getExtensionPlaceholder(DtoExtensionPlaceholder::class);
		$extPlaceholder->dtoClassName = $this->dtoClassName;
	}


	public function getIncludedClassNames(): \Generator
	{
		if ($this->dtoClassName) {
			yield $this->dtoClassName;
		}
	}

}
