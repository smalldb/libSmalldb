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
use Smalldb\StateMachine\CodeGenerator\Annotation\GeneratedClass;
use Smalldb\StateMachine\Utils\AnnotationReader\AnnotationReaderInterface;
use Smalldb\StateMachine\Utils\ClassLocator\ClassLocator;
use Smalldb\StateMachine\Utils\AnnotationReader\AnnotationReader;


class RecipeLocator
{

	/** @var ClassLocator[] */
	private array $classLocators = [];

	/** @var AnnotationHandler[][] */
	private array $annotationHandlers = [];

	private AnnotationReaderInterface $annotationReader;


	public function __construct(AnnotationReaderInterface $annotationReader = null)
	{
		$this->annotationReader = $annotationReader ?? (new AnnotationReader());
		$this->addAnnotationHandler(new DtoGenerator($this->annotationReader));
	}


	public function getAnnotationReader(): AnnotationReaderInterface
	{
		return $this->annotationReader;
	}


	public function addClassLocator(ClassLocator $classLocator): void
	{
		$this->classLocators[] = $classLocator;
	}


	public function addAnnotationHandler(AnnotationHandler $annotationHandler): void
	{
		foreach ($annotationHandler->getSupportedAnnotations() as $annotationClassName) {
			$this->annotationHandlers[$annotationClassName][] = $annotationHandler;
		}
	}


	public function locateClasses(): iterable
	{
		foreach ($this->classLocators as $classLocator) {
			foreach ($classLocator->getClasses() as $classname) {
				yield $classname;
			}
		}
	}


	public function locateRecipes(): \Generator
	{
		foreach ($this->locateClasses() as $className) {
			yield from $this->locateClassRecipes(new ReflectionClass($className));
		}
	}


	/**
	 * @return ReflectionClass[]
	 */
	public function locateGeneratedClasses(): iterable
	{
		foreach ($this->locateClasses() as $className) {
			$class = new ReflectionClass($className);
			if ($this->hasGeneratedAnnotation($class)) {
				yield $class;
			}
		}
	}


	private function hasGeneratedAnnotation(ReflectionClass $class): bool
	{
		$annotations = $this->annotationReader->getClassAnnotations($class);
		foreach ($annotations as $annotation) {
			if ($annotation instanceof GeneratedClass) {
				return true;
			}
		}
		return false;
	}


	/**
	 * @return string[]  List of generated classes (FQCNs)
	 */
	public function locateClassRecipes(ReflectionClass $sourceClass): \Generator
	{
		$annotations = $this->annotationReader->getClassAnnotations($sourceClass);

		foreach ($annotations as $annotation) {
			if ($annotation instanceof GeneratedClass) {
				// Do not process generated classes
				return;
			}
		}

		foreach ($annotations as $annotation) {
			$annotationClassName = get_class($annotation);
			if ($annotation instanceof AnnotationRecipeBuilder) {
				yield $annotation->buildRecipe($sourceClass);
			} else if (isset($this->annotationHandlers[$annotationClassName])) {
				foreach ($this->annotationHandlers[$annotationClassName] as $handler) {
					yield $handler->handleClassAnnotation($sourceClass, $annotation);
				}
			}
		}
	}


	public function deleteGeneratedClasses(): void
	{
		foreach ($this->locateGeneratedClasses() as $generatedClass) {
			unlink($generatedClass->getFileName());
		}
	}

}
