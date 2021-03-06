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

namespace Smalldb\CodeCooker\Recipe;

use ReflectionClass;
use Smalldb\CodeCooker\Generator\DtoGenerator;
use Smalldb\ClassLocator\ClassLocator;


class DtoRecipe extends ClassRecipe
{
	private ?string $targetName;


	public function __construct(array $targetClassNames, string $sourceClassName, string $targetName = null)
	{
		parent::__construct($sourceClassName, $targetClassNames);
		$this->targetName = $targetName;
	}


	public static function fromReflection(ReflectionClass $sourceClass, string $targetName = null): self
	{
		$targetClassNames = DtoGenerator::calculateTargetClassNames($sourceClass, $targetName);
		return new self($targetClassNames, $sourceClass->getName(), $targetName);
	}


	public function cookRecipe(ClassLocator $classLocator): array
	{
		$generator = new DtoGenerator($classLocator);
		return $generator->generateDtoClasses($this->getSourceClass(), $this->getTargetName());
	}


	public function getTargetName(): ?string
	{
		return $this->targetName;
	}

}
