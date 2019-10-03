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

use Psr\Container\ContainerInterface;
use Smalldb\StateMachine\Utils\AnnotationReader\AnnotationReaderInterface;
use Smalldb\StateMachine\Utils\AnnotationReader\AnnotationReader;


class InferClass
{

	/** @var ClassLocator[] */
	private $classLocators;

	/** @var \Smalldb\StateMachine\Utils\\Smalldb\StateMachine\Utils\AnnotationReader\AnnotationReaderInterface */
	private $annotationReader;

	/** @var ContainerInterface */
	private $classGeneratorServiceLocator;


	public function __construct(ContainerInterface $classGeneratorServiceLocator = null, AnnotationReaderInterface $annotationReader = null)
	{
		$this->classGeneratorServiceLocator = $classGeneratorServiceLocator;
		$this->annotationReader = $annotationReader ?? (new AnnotationReader());
	}


	public function addClassLocator(ClassLocator $classLocator): void
	{
		$this->classLocators[] = $classLocator;
	}


	public function locateClasses(): iterable
	{
		foreach ($this->classLocators as $classLocator) {
			foreach ($classLocator->getClasses() as $classname) {
				yield $classname;
			}
		}
	}


	public function processClasses(): void
	{
		foreach ($this->locateClasses() as $className) {
			$this->processClass(new \ReflectionClass($className));
		}
	}


	public function processClass(\ReflectionClass $class): void
	{
		$annotations = $this->annotationReader->getClassAnnotations($class);

		foreach ($annotations as $annotation) {
			if ($annotation instanceof InferClassAnnotation) {
				$classGeneratorName = $annotation->getInferClassGeneratorName();
				$classGenerator = $this->getClassGenerator($classGeneratorName);
				$classGenerator->processClass($class, $annotation, $this->annotationReader);
			}
		}
	}


	protected function getClassGenerator(string $className): InferClassGenerator
	{
		if ($this->classGeneratorServiceLocator && $this->classGeneratorServiceLocator->has($className)) {
			return $this->classGeneratorServiceLocator->get($className);
		} else {
			return new $className();
		}
	}

}
