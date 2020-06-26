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

use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\InvalidArgumentException;
use Smalldb\StateMachine\RuntimeException;
use Smalldb\StateMachine\SmalldbDefinitionBag;
use Smalldb\PhpFileWriter\PhpFileWriter;


/**
 * A PSR-4-friendly class generator.
 */
class SmalldbClassGenerator
{
	/** @var string */
	private $classDirectory;

	/** @var string */
	private $classNamespace;

	/** @var string[] */
	private $classFiles = [];

	/** @var ReferenceClassGenerator|null */
	private $referenceClassGenerator = null;


	public function __construct(string $classNamespace, string $classDirectory)
	{
		$this->classNamespace = trim($classNamespace, '\\');
		$this->classDirectory = $classDirectory;

		if (!is_dir($this->classDirectory)) {
			mkdir($this->classDirectory);  // @codeCoverageIgnore
		}
	}


	public function getClassNamespace(): string
	{
		return $this->classNamespace;
	}


	public function getClassDirecotry(): string
	{
		return $this->classDirectory;
	}


	public function addGeneratedClass(string $className, string $classContent)
	{
		$shortClassName = PhpFileWriter::getShortClassName($className);
		$namespace = PhpFileWriter::getClassNamespace($className);

		if ($namespace !== $this->classNamespace) {
			throw new InvalidArgumentException("The generated class $className must be in $this->classNamespace namespace.");  // @codeCoverageIgnore
		}

		$filename = $shortClassName . '.php';
		if (file_put_contents($this->classDirectory . '/' . $filename, $classContent) === false) {
			throw new RuntimeException("Failed to store class: $filename");  // @codeCoverageIgnore
		}

		$this->classFiles[$className] = $filename;
	}


	public function generateDefinitionBag(SmalldbDefinitionBag $definitionBag,
		string $bagShortClassName = 'GeneratedDefinitionBag'): string
	{
		$loader = new DefinitionBagGenerator($this);
		$generatedClass = $loader->generateDefinitionBagClass($this->classNamespace . '\\' . $bagShortClassName, $definitionBag);
		$this->checkGeneratedClass($generatedClass);
		return $generatedClass;
	}


	public function generateReferenceClass(string $sourceReferenceClass, StateMachineDefinition $definition): string
	{
		if (!$this->referenceClassGenerator) {
			$this->referenceClassGenerator = new ReferenceClassGenerator($this);
		}

		$generatedClass = $this->referenceClassGenerator->generateReferenceClass($sourceReferenceClass, $definition);
		$this->checkGeneratedClass($generatedClass);
		return $generatedClass;
	}


	private function checkGeneratedClass(string $generatedClass)
	{
		if (!class_exists($generatedClass)) {
			// @codeCoverageIgnoreStart
			throw new RuntimeException("Generated class $generatedClass not found. "
				. "Is class loader configured to load the generated classes? "
				. "Configure PSR-4 loader to load $this->classNamespace from $this->classDirectory");
			// @codeCoverageIgnoreEnd
		}
	}

}
