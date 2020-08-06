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

namespace Smalldb\ClassLocator;


class ClassMapClassLocator implements ClassLocator
{
	use BrokenClassHandlerTrait;

	/** @var string[] */
	private array $classToFilenameMap;

	/** @var string[] */
	private array $filenameToClassMap;


	public function __construct(array $classMap)
	{
		$this->classToFilenameMap = $classMap;
		$this->filenameToClassMap = array_flip($classMap);
	}


	public function getClasses(): \Generator
	{
		foreach ($this->classToFilenameMap as $className => $fileName) {
			yield $fileName => $className;
		}
	}


	public function mapClassNameToFileName(string $className): ?string
	{
		return $this->classToFilenameMap[$className] ?? null;
	}


	public function mapFileNameToClassName(string $fileName): ?string
	{
		// TODO: Normalize filenames to handle 'a/b/../c.php' == 'a/c.php'
		return $this->filenameToClassMap[$fileName] ?? null;
	}

}
