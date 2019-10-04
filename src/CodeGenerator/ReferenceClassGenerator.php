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

namespace Smalldb\StateMachine\CodeGenerator;

use ReflectionClass;
use Smalldb\StateMachine\CodeGenerator\ReferenceClassGenerator\DecoratingGenerator;
use Smalldb\StateMachine\CodeGenerator\ReferenceClassGenerator\DummyGenerator;
use Smalldb\StateMachine\CodeGenerator\ReferenceClassGenerator\InheritingGenerator;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\InvalidArgumentException;
use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\ReferenceTrait;
use Smalldb\StateMachine\Utils\PhpFileWriter;


class ReferenceClassGenerator extends AbstractClassGenerator
{

	/** @var InheritingGenerator */
	private $inheritingGenerator = null;

	/** @var DecoratingGenerator */
	private $decoratingGenerator = null;

	/** @var DummyGenerator */
	private $dummyGenerator = null;


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
	 * @throws LogicException
	 */
	public function generateReferenceClass(string $sourceReferenceClassName, StateMachineDefinition $definition): string
	{
		try {
			$generator = null;
			$sourceClassReflection = new ReflectionClass($sourceReferenceClassName);

			$parentClassReflection = $sourceClassReflection->getParentClass();
			if ($parentClassReflection && !$parentClassReflection->implementsInterface(ReferenceInterface::class)) {
				// If parent class is not a reference, then it is DTO extended into a Reference object.
				return $this->getInheritingGenerator()->generateReferenceClass($sourceReferenceClassName, $definition);
			} else {
				$implementsInterface = [];
				foreach ($sourceClassReflection->getInterfaces() as $interfaceReflection) {
					if ($interfaceReflection->getName() !== ReferenceInterface::class) {
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
