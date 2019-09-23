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

namespace Smalldb\StateMachine\AnnotationReader;

use ReflectionClass;
use Smalldb\StateMachine\Annotation\StateMachine;
use Smalldb\StateMachine\Annotation\State;
use Smalldb\StateMachine\Annotation\Transition;
use Smalldb\StateMachine\Definition\Builder\ActionPlaceholderApplyInterface;
use Smalldb\StateMachine\Definition\Builder\PropertyPlaceholderApplyInterface;
use Smalldb\StateMachine\Definition\Builder\StateMachineBuilderApplyInterface;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilder;
use Smalldb\StateMachine\Definition\Builder\StatePlaceholderApplyInterface;
use Smalldb\StateMachine\Definition\Builder\TransitionPlaceholderApplyInterface;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\InvalidArgumentException;
use Smalldb\StateMachine\Utils\DeepAnnotationReader;


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


	public function __construct(string $className)
	{
		$this->className = $className;

	}


	public function getStateMachineDefinition(): StateMachineDefinition
	{
		$this->builder = new StateMachineDefinitionBuilder();
		$this->processClassReflection(new ReflectionClass($this->className));
		return $this->builder->build();
	}


	private function processClassReflection(ReflectionClass $reflectionClass): void
	{
		$filename = $reflectionClass->getFileName();
		if ($filename === false) {
			throw new InvalidArgumentException("Cannot process PHP core or extension class: " . $reflectionClass->getName());  //@codeCoverageIgnore
		}
		$this->classFileName = $filename;
		$this->baseDir = dirname($this->classFileName);
		$this->builder->setReferenceClass($reflectionClass->getName());

		$reader = new DeepAnnotationReader();

		$this->processClassAnnotations($reflectionClass, $reader->getClassAnnotations($reflectionClass));

		foreach ($reflectionClass->getReflectionConstants() as $reflectionConstant) {
			$this->processConstantAnnotations($reflectionConstant, $reader->getConstantAnnotations($reflectionConstant));
		}

		foreach ($reflectionClass->getMethods() as $reflectionMethod) {
			if (!$reflectionMethod->isStatic()) {
				$this->processMethodAnnotations($reflectionMethod, $reader->getMethodAnnotations($reflectionMethod));
			}
		}

		foreach ($reflectionClass->getProperties() as $reflectionProperty) {
			if (!$reflectionProperty->isStatic()) {
				$this->processPropertyAnnotations($reflectionProperty, $reader->getPropertyAnnotations($reflectionProperty));
			}
		}

	}

	public function processClassAnnotations(ReflectionClass $reflectionClass, array $annotations): void
	{
		foreach ($annotations as $annotation) {
			if ($annotation instanceof ReflectionClassAwareAnnotationInterface) {
				$annotation->setReflectionClass($reflectionClass);
			}
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
			if ($annotation instanceof ReflectionConstantAwareAnnotationInterface) {
				$annotation->setReflectionConstant($reflectionConstant);
			}
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
			if ($annotation instanceof ReflectionMethodAwareAnnotationInterface) {
				$annotation->setReflectionMethod($reflectionMethod);
			}
			if ($annotation instanceof Transition) {
				$isTransition = true;
				if ($annotation->definesTransition()) {
					$transitionPlaceholders[] = $this->builder->addTransition($transitionName, $annotation->source, $annotation->targets, $annotation->color);
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


	public function processPropertyAnnotations(\ReflectionProperty $reflectionProperty, array $annotations): void
	{
		foreach ($annotations as $annotation) {
			if ($annotation instanceof ReflectionPropertyAwareAnnotationInterface) {
				$annotation->setReflectionProperty($reflectionProperty);
			}
		}

		$name = $reflectionProperty->getName();

		// Get getter type as default property type
		// TODO: When PHP 7.4 comes, use property typehint first.
		$getterName = 'get' . ucfirst($name);
		$classReflection = $reflectionProperty->getDeclaringClass();
		if ($classReflection->hasMethod($getterName) && ($type = $classReflection->getMethod($getterName)->getReturnType())) {
			$placeholder = $this->builder->addProperty($name, $type->getName(), $type->allowsNull());
		} else {
			$placeholder = $this->builder->addProperty($name);
		}

		foreach ($annotations as $annotation) {
			if ($annotation instanceof PropertyPlaceholderApplyInterface) {
				$annotation->applyToPropertyPlaceholder($placeholder);
			}
		}
	}

}
