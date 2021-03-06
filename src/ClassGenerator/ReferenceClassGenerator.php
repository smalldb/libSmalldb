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

namespace Smalldb\StateMachine\ClassGenerator;

use ReflectionClass;
use Smalldb\StateMachine\ClassGenerator\ReferenceClassGenerator\DecoratingGenerator;
use Smalldb\StateMachine\ClassGenerator\ReferenceClassGenerator\DummyGenerator;
use Smalldb\StateMachine\ClassGenerator\ReferenceClassGenerator\InheritingGenerator;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\DtoExtension\Definition\DtoExtension;
use Smalldb\StateMachine\InvalidArgumentException;
use Smalldb\StateMachine\MachineIdentifierInterface;
use Smalldb\StateMachine\ReferenceInterface;


class ReferenceClassGenerator extends AbstractClassGenerator
{
	private ?InheritingGenerator $inheritingGenerator = null;
	private ?DecoratingGenerator $decoratingGenerator = null;
	private ?DummyGenerator $dummyGenerator = null;


	public function __construct(SmalldbClassGenerator $classGenerator)
	{
		parent::__construct($classGenerator);
	}


	protected function getDecoratingGenerator(): DecoratingGenerator
	{
		return $this->decoratingGenerator
			?? ($this->decoratingGenerator = new DecoratingGenerator($this->getClassGenerator()));
	}


	protected function getInheritingGenerator(): InheritingGenerator
	{
		return $this->inheritingGenerator
			?? ($this->inheritingGenerator = new InheritingGenerator($this->getClassGenerator()));
	}


	protected function getDummyGenerator(): DummyGenerator
	{
		return $this->dummyGenerator
			?? ($this->dummyGenerator = new DummyGenerator($this->getClassGenerator()));
	}


	/**
	 * Generate a new class implementing the missing methods in $sourceReferenceClassName.
	 *
	 * @return string Class name of the implementation.
	 * @throws ReflectionException
	 */
	public function generateReferenceClass(string $sourceReferenceClassName, StateMachineDefinition $definition): string
	{
		try {
			$generator = null;
			$sourceClassReflection = new ReflectionClass($sourceReferenceClassName);

			if ($definition->hasExtension(DtoExtension::class)) {
				return $this->getDecoratingGenerator()->generateReferenceClass($sourceReferenceClassName, $definition);
			}

			$parentClassReflection = $sourceClassReflection->getParentClass();
			if ($parentClassReflection && !$parentClassReflection->implementsInterface(ReferenceInterface::class)) {
				// If parent class is not a reference, then it is DTO extended into a Reference object.
				return $this->getInheritingGenerator()->generateReferenceClass($sourceReferenceClassName, $definition);
			} else {
				$implementsInterface = [];
				foreach ($sourceClassReflection->getInterfaces() as $interfaceReflection) {
					if (!$interfaceReflection->implementsInterface(ReferenceInterface::class)
						&& $interfaceReflection->getName() !== MachineIdentifierInterface::class)
					{
						$implementsInterface[] = $interfaceReflection;
					}
				}
				switch (count($implementsInterface)) {
					case 0:
						// No DTO detected, generate an empty Reference object.
						return $this->getDummyGenerator()->generateReferenceClass($sourceReferenceClassName, $definition);

					case 1:
						// If there is a single interface other than ReferenceInterface,
						// then the reference decorates this interface.
						return $this->getDecoratingGenerator()->generateReferenceClass($sourceReferenceClassName, $definition);

				}
			}

			throw new InvalidArgumentException("Failed to detect the desired Reference implementation: $sourceReferenceClassName");
		}
		// @codeCoverageIgnoreStart
		catch (\ReflectionException $ex) {
			throw new ReflectionException("Failed to generate Smalldb reference class: " . $definition->getMachineType(), 0, $ex);
		}
		// @codeCoverageIgnoreEnd
	}


}
