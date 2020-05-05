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
use Smalldb\StateMachine\Definition\Builder\Preprocessor;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilderFactory;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Utils\ClassLocator\ClassLocator;


class SmalldbDefinitionBagReader
{

	/** @var SmalldbDefinitionBag */
	private $definitionBag;

	/** @var StateMachineDefinitionBuilderFactory */
	private $definitionBuilderFactory;

	/** @var AnnotationReader */
	private $annotationReader;

	public function __construct()
	{
		$this->definitionBag = new SmalldbDefinitionBag();
		$this->definitionBuilderFactory = StateMachineDefinitionBuilderFactory::createDefaultFactory();
		$this->annotationReader = new AnnotationReader($this->definitionBuilderFactory);
	}


	public function addPreprocessor(Preprocessor $preprocessor): void
	{
		$this->definitionBuilderFactory->addPreprocessor($preprocessor);
	}


	public function getDefinitionBag(): SmalldbDefinitionBag
	{
		return $this->definitionBag;
	}


	public function addFromAnnotatedClass(string $className): StateMachineDefinition
	{
		$definition = $this->annotationReader->getStateMachineDefinition($className);
		try {
			$machineType = $this->definitionBag->addDefinition($definition);
			if ($machineType !== $className) {
				$this->definitionBag->addAlias($className, $machineType);
			}
		}
		catch(InvalidArgumentException $ex) {
			throw new InvalidArgumentException($className . ": " . $ex->getMessage(), $ex->getCode(), $ex);
		}
		return $definition;
	}


	/**
	 * @return StateMachineDefinition[]
	 */
	public function addFromAnnotatedClasses(iterable $classNames): array
	{
		$foundDefinitions = [];

		foreach ($classNames as $className) {
			try {
				$foundDefinitions[$className] = $this->addFromAnnotatedClass($className);
			}
			catch (MissingStateMachineAnnotationException $ex) {
				// Ignore classes without @StateMachine annotation.
			}
		}

		return $foundDefinitions;
	}


	/**
	 * @return StateMachineDefinition[]
	 */
	public function addFromClassLocator(ClassLocator $classLocator): array
	{
		return $this->addFromAnnotatedClasses($classLocator->getClasses());
	}

}