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

use Smalldb\StateMachine\Definition\AnnotationReader\ReflectionClassAwareAnnotationInterface;


abstract class AbstractIncludeAnnotation implements ReflectionClassAwareAnnotationInterface
{

	protected ?string $baseDirName = null;


	public function setReflectionClass(\ReflectionClass $reflectionClass)
	{
		$this->baseDirName = dirname($reflectionClass->getFileName());
	}


	protected function canonizeFileName(?string $fileName): ?string
	{
		if ($fileName === null) {
			return null;
		}

		if ($fileName[0] !== DIRECTORY_SEPARATOR) {
			$fileName = $this->baseDirName ? $this->baseDirName . DIRECTORY_SEPARATOR . $fileName : $fileName;

			// Try to resolve relative path to current working directory
			$cwd = getcwd();
			if ($cwd === false) {
				return $fileName;  // @codeCoverageIgnore
			}
			$realCwd = realpath($cwd) . DIRECTORY_SEPARATOR;
			$realFileName = realpath($fileName) ?: $fileName;
			if (strpos($realFileName, $realCwd) === 0) {
				return substr($realFileName, strlen($realCwd));
			}
		}
		return $fileName;
	}

}
