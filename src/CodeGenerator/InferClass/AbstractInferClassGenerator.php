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

namespace Smalldb\StateMachine\CodeGenerator\InferClass;

use ReflectionClass;
use Smalldb\StateMachine\Utils\PhpFileWriter;


abstract class AbstractInferClassGenerator implements InferClassGenerator
{

	protected function getTargetDirectory(ReflectionClass $sourceClass): string
	{
		$targetDir = dirname($sourceClass->getFileName()) . DIRECTORY_SEPARATOR . $sourceClass->getShortName();
		if (!is_dir($targetDir)) {
			mkdir($targetDir);
		}
		return $targetDir;
	}


	protected function createFileWriter(InferClassAnnotation $annotationReflection, string $targetNamespace): PhpFileWriter
	{
		$w = new PhpFileWriter();
		$w->setFileHeader(get_class($this) . ' (@' . get_class($annotationReflection) . ' annotation)');
		$w->setNamespace($targetNamespace);
		return $w;
	}

}
