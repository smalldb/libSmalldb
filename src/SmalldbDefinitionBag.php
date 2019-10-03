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

namespace Smalldb\StateMachine;

use Smalldb\StateMachine\Definition\AnnotationReader\AnnotationReader;
use Smalldb\StateMachine\Definition\AnnotationReader\MissingStateMachineAnnotationException;
use Smalldb\StateMachine\Definition\StateMachineDefinition;


class SmalldbDefinitionBag implements SmalldbDefinitionBagInterface
{
	/** @var StateMachineDefinition[] */
	private $definitionBag = [];

	/** @var string[] */
	private $aliases = [];


	public function __construct()
	{
	}


	public function getDefinition(string $machineType): StateMachineDefinition
	{
		if (isset($this->definitionBag[$machineType])) {
			return $this->definitionBag[$machineType];
		} else if (isset($this->aliases[$machineType])) {
			return $this->definitionBag[$this->aliases[$machineType]];
		} else {
			throw new InvalidArgumentException("Undefined machine type: $machineType");
		}
	}


	/**
	 * @return StateMachineDefinition[]
	 */
	public function getAllDefinitions(): array
	{
		return $this->definitionBag;
	}


	/**
	 * @return string[]
	 */
	public function getAllAliases(): array
	{
		return $this->aliases;
	}


	/**
	 * @return string[]
	 */
	public function getAllMachineTypes(): array
	{
		return array_keys($this->definitionBag);
	}


	public function addDefinition(StateMachineDefinition $definition): string
	{
		$machineType = $definition->getMachineType();
		if (isset($this->definitionBag[$machineType])) {
			throw new InvalidArgumentException("Duplicate state machine type: $machineType");
		} else {
			$this->definitionBag[$machineType] = $definition;
			return $machineType;
		}
	}


	public function addAlias(string $alias, string $machineType)
	{
		if (isset($this->aliases[$alias])) {
			throw new InvalidArgumentException("Duplicate state machine alias: $alias");
		} else if (isset($this->definitionBag[$machineType])) {
			$this->aliases[$alias] = $machineType;
		} else {
			throw new InvalidArgumentException("Undefined machine type: $machineType");
		}
	}


	public function addFromAnnotatedClass(string $className): StateMachineDefinition
	{
		$reader = new AnnotationReader();
		$definition = $reader->getStateMachineDefinition($className);
		$machineType = $this->addDefinition($definition);
		$this->addAlias($className, $machineType);
		return $definition;
	}

	public function addFromPsr4Directory(string $namespace, string $directory, array &$foundDefinitions = []): array
	{
		if (!is_dir($directory)) {
			throw new InvalidArgumentException("Not a directory: $directory");
		}

		if ($namespace[-1] !== "\\") {
			$namespace .= "\\";
		}

		foreach (scandir($directory) as $f) {
			if ($f[0] === '.') {
				continue;
			}
			$fullPath = "$directory/$f";
			$dotPos = strpos($f, '.');
			$className = $namespace . ($dotPos ? substr($f, 0, $dotPos) : $f);
			if (is_dir($fullPath)) {
				$this->addFromPsr4Directory($className, $fullPath, $foundDefinitions);
			} else if ($dotPos && substr($f, $dotPos) === '.php' && (class_exists($className) || interface_exists($className))) {
				try {
					$foundDefinitions[$className] = $this->addFromAnnotatedClass($className);
				}
				catch (MissingStateMachineAnnotationException $ex) {
					// Ignore classes without @StateMachine annotation.
				}
			}
		}

		return $foundDefinitions;
	}

}
