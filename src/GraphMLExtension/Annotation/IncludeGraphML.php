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

use Smalldb\StateMachine\AnnotationReader\ReflectionClassAwareAnnotationInterface;
use Smalldb\StateMachine\Definition\Builder\StateMachineBuilderApplyInterface;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilder;
use Smalldb\StateMachine\GraphMLExtension\GraphMLExtensionPlaceholder;


/**
 * Include GraphML state chart file
 *
 * @Annotation
 * @Target({"CLASS"})
 */
class IncludeGraphML implements StateMachineBuilderApplyInterface, ReflectionClassAwareAnnotationInterface
{
	/** @var string */
	public $fileName;

	/** @var string|null */
	public $group = null;

	/** @var string */
	private $baseDirName;


	public function setReflectionClass(\ReflectionClass $reflectionClass)
	{
		$this->baseDirName = dirname($reflectionClass->getFileName());
	}


	public function applyToBuilder(StateMachineDefinitionBuilder $builder): void
	{
		if ($this->fileName[0] === DIRECTORY_SEPARATOR) {
			$fullFileName = $fileName = $this->fileName;  // @codeCoverageIgnore
		} else {
			$fullFileName = $fileName = $this->baseDirName . DIRECTORY_SEPARATOR . $this->fileName;
		}

		// Try to resolve relative path to current working directory
		$cwd = realpath(getcwd()) . DIRECTORY_SEPARATOR;
		$realFileName = realpath($fullFileName);
		if (strpos($realFileName, $cwd) === 0) {
			$fileName = substr($realFileName, strlen($cwd));
		}

		/** @var GraphMLExtensionPlaceholder $placeholder */
		$placeholder = $builder->getExtensionPlaceholder(GraphMLExtensionPlaceholder::class);
		$placeholder->fileName = $fileName;
		$placeholder->group = $this->group;

		$builder->addPreprocessor(new GraphMLPreprocessor($fullFileName, $this->group));
	}

}
