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

use Doctrine\Common\Annotations\AnnotationReader as DoctrineAnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use ReflectionClass;
use Smalldb\StateMachine\Annotation\StateMachine;
use Smalldb\StateMachine\Annotation\State;
use Smalldb\StateMachine\Annotation\Transition;
use Smalldb\StateMachine\Definition\Builder\ActionPlaceholderApplyInterface;
use Smalldb\StateMachine\Definition\Builder\StateMachineBuilderApplyInterface;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilder;
use Smalldb\StateMachine\Definition\Builder\StatePlaceholderApplyInterface;
use Smalldb\StateMachine\Definition\Builder\TransitionPlaceholderApplyInterface;
use Smalldb\StateMachine\Definition\StateMachineDefinition;


/**
 * Construct state machine definition from interface annotations.
 *   - The interface with @StateMachine annotation represents the machine.
 *   - Methods with a @Transition annotation are transitions.
 *   - Constants with a @State annotation are states.
 */
class AnnotationReader
{
	/** @var string */
	private $className;

	/**
	 * Name of the file where the interface is defined.
	 * @var string
	 */
	private $classFileName;

	/**
	 * All file paths in the annotations are relative to the $baseDir.
	 * @var string
	 */
	private $baseDir;

	/**
	 * @var StateMachineDefinitionBuilder
	 */
	private $builder;

	/**
	 *
	 * @var bool
	 */
	private static $needAnnotationsInitialization = true;


	public function __construct(string $className)
	{
		$this->className = $className;

	}


	private function createAnnotationReader(): DoctrineAnnotationReader
	{
		// Use autoloader to load annotations
		if (static::$needAnnotationsInitialization) {
			static::$needAnnotationsInitialization = false;
			if (class_exists(AnnotationRegistry::class)) {
				AnnotationRegistry::registerUniqueLoader('class_exists');
			}
		}

		return new DoctrineAnnotationReader();
	}


	private function processClassReflection(ReflectionClass $reflectionClass): void
	{
		$this->classFileName = $reflectionClass->getFileName();
		$this->baseDir = dirname($this->classFileName);
		$this->builder->setReferenceClass($reflectionClass->getName());

		$reader = $this->createAnnotationReader();

		$this->processClassAnnotations($reflectionClass, $reader->getClassAnnotations($reflectionClass));

		foreach ($reflectionClass->getReflectionConstants() as $reflectionConstant) {
			$this->processConstantAnnotations($reflectionConstant, $reader->getConstantAnnotations($reflectionConstant));
		}

		foreach ($reflectionClass->getMethods() as $reflectionMethod) {
			$this->processMethodAnnotations($reflectionMethod, $reader->getMethodAnnotations($reflectionMethod));
		}

	}

	public function processClassAnnotations(ReflectionClass $reflectionClass, array $annotations): void
	{
		foreach ($annotations as $annotation) {
			if ($annotation instanceof StateMachineBuilderApplyInterface) {
				$annotation->applyToBuilder($this->builder);
			}
		}
	}

	public function processConstantAnnotations(\ReflectionClassConstant $reflectionConstant, array $annotations): void
	{
		// Find & use @State annotation and make sure there is only one of the kind
		$placeholder = null;
		foreach ($annotations as $annotation) {
			if ($annotation instanceof State) {
				if ($placeholder) {
					throw new \InvalidArgumentException("Multiple @State annotations at " . $reflectionConstant->getName() . " constant.");
				} else {
					$stateName = $annotation->name ?? (string) $reflectionConstant->getValue();
					$placeholder = $this->builder->addState($stateName);
				}
			}
		}

		if (!$placeholder) {
			return; // This is not a state of the state machine.
		}

		// Apply all annotations to the state placeholder
		foreach ($annotations as $annotation) {
			if ($annotation instanceof StatePlaceholderApplyInterface) {
				$annotation->applyToStatePlaceholder($placeholder);
			}
		}
	}

	public function processMethodAnnotations(\ReflectionMethod $reflectionMethod, array $annotations): void
	{
		// Find & use @Transition annotations
		$transitionPlaceholders = [];
		$actionPlaceholders = [];
		$isTransition = false;
		$transitionName = $reflectionMethod->getName();
		foreach ($annotations as $annotation) {
			if ($annotation instanceof Transition) {
				$isTransition = true;
				if ($annotation->definesTransition()) {
					$transitionPlaceholders[] = $this->builder->addTransition($transitionName, $annotation->source, $annotation->targets);
				} else {
					$actionPlaceholders[] = $this->builder->addAction($transitionName);
				}
			}
		}

		if (!$isTransition) {
			return; // This is not a transition.
		}

		foreach ($annotations as $annotation) {
			// Apply all annotations to the action placeholder
			if ($annotation instanceof ActionPlaceholderApplyInterface) {
				foreach ($actionPlaceholders as $placeholder) {
					$annotation->applyToActionPlaceholder($placeholder);
				}
			}

			// Apply all annotations to the transition placeholders
			if ($annotation instanceof TransitionPlaceholderApplyInterface) {
				foreach ($transitionPlaceholders as $placeholder) {
					$annotation->applyToTransitionPlaceholder($placeholder);
				}
			}
		}
	}


	public function getStateMachineDefinition(): StateMachineDefinition
	{
		$this->builder = new StateMachineDefinitionBuilder();
		$this->processClassReflection(new ReflectionClass($this->className));
		return $this->builder->build();
	}

}
