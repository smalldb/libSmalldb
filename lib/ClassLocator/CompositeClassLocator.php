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

class CompositeClassLocator implements ClassLocator
{
	/** @var ClassLocator[] */
	private array $classLocators = [];
	private ?BrokenClassHandlerInterface $brokenClassHandler = null;


	/**
	 * @param ClassLocator[] $classLocators
	 */
	public function __construct(array $classLocators = [])
	{
		foreach ($classLocators as $classLocator) {
			$this->addClassLocator($classLocator);
		}
	}


	public function setBrokenClassHandler(?BrokenClassHandlerInterface $brokenClassHandler)
	{
		$this->brokenClassHandler = $brokenClassHandler;
		foreach ($this->classLocators as $classLocator) {
			$classLocator->setBrokenClassHandler($this->brokenClassHandler);
		}
	}


	public function addClassLocator(ClassLocator $classLocator)
	{
		$this->classLocators[] = $classLocator;
		$classLocator->setBrokenClassHandler($this->brokenClassHandler);
	}


	/**
	 * @return ClassLocator[]
	 */
	public function getClassLocators(): array
	{
		return $this->classLocators;
	}


	public function getClasses(): \Generator
	{
		foreach ($this->classLocators as $classLocator) {
			yield from $classLocator->getClasses();
		}
	}


	public function mapClassNameToFileName(string $className): ?string
	{
		foreach ($this->classLocators as $classLocator) {
			$fileName = $classLocator->mapClassNameToFileName($className);
			if ($fileName !== null) {
				return $fileName;
			}
		}
		return null;
	}


	public function mapFileNameToClassName(string $fileName): ?string
	{
		foreach ($this->classLocators as $classLocator) {
			$className = $classLocator->mapFileNameToClassName($fileName);
			if ($className !== null) {
				return $className;
			}
		}
		return null;
	}

}
