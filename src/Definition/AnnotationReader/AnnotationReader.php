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

namespace Smalldb\StateMachine\Definition\AnnotationReader;

use ReflectionClass;
use ReflectionClassConstant;
use ReflectionMethod;
use ReflectionProperty;
use Smalldb\StateMachine\Annotation\StateMachine;
use Smalldb\StateMachine\Annotation\State;
use Smalldb\StateMachine\Annotation\Transition;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilder;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilderFactory;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\InvalidArgumentException;
use Smalldb\StateMachine\ReferenceTrait;
use Smalldb\StateMachine\SourcesExtension\Definition\SourceClassFile;
use Smalldb\StateMachine\SourcesExtension\Definition\SourcesExtensionPlaceholder;
use Smalldb\StateMachine\Utils\AnnotationReader\AnnotationReaderInterface;
use Smalldb\StateMachine\Utils\AnnotationReader\AnnotationReader as Reader;


/**
 * Construct state machine definition from interface annotations.
 *   - The interface with `@StateMachine` annotation represents the machine.
 *   - Methods with a `@Transition` annotation are transitions.
 *   - Constants with a `@State` annotation are states.
 */
class AnnotationReader
{
	private StateMachineDefinitionBuilderFactory $definitionBuilderFactory;
	private AnnotationReaderInterface $annotationReader;


	public function __construct(StateMachineDefinitionBuilderFactory $definitionBuilderFactory,
		?AnnotationReaderInterface $annotationReader = null)
	{
		$this->definitionBuilderFactory = $definitionBuilderFactory;
		$this->annotationReader = $annotationReader ?? (new Reader());
	}


	public function getStateMachineDefinition(string $className): StateMachineDefinition
	{
		$reflectionClass = new ReflectionClass($className);
		$builder = $this->definitionBuilderFactory->createDefinitionBuilder();
		$this->processClassReflection($reflectionClass, $this->annotationReader, $builder);
		return $builder->build();
	}


	private function processClassReflection(ReflectionClass $reflectionClass,
		AnnotationReaderInterface $annotationReader, StateMachineDefinitionBuilder $builder,
		bool $isSourceClass = true): void
	{
		$filename = $reflectionClass->getFileName();
		$classname = $reflectionClass->getName();

		// Disallow internal classes
		if ($filename === false) {
			throw new InvalidArgumentException("Cannot process PHP core or extension class: $classname");  //@codeCoverageIgnore
		}

		// Disallow the use of ReferenceTrait
		$traits = $reflectionClass->getTraits();
		foreach ($traits as $trait) {
			if ($trait->getName() === ReferenceTrait::class) {
				throw new InvalidArgumentException("Reference class $classname must not use ReferenceTrait. Use ReferenceProtectedAPI instead.");
			}
		}

		if ($isSourceClass) {
			$builder->setReferenceClass($classname);
			$builder->setMTime(filemtime($filename));
		}

		/** @var SourcesExtensionPlaceholder $sourcesPlaceholder */
		$sourcesPlaceholder = $builder->getExtensionPlaceholder(SourcesExtensionPlaceholder::class);
		$sourcesPlaceholder->addSourceFile(new SourceClassFile($reflectionClass));

		$classAnnotations = $annotationReader->getClassAnnotations($reflectionClass);
		$this->processClassAnnotations($reflectionClass, $classAnnotations, $builder, $isSourceClass);

		foreach ($reflectionClass->getReflectionConstants() as $reflectionConstant) {
			$constantAnnotations = $annotationReader->getConstantAnnotations($reflectionConstant);
			$this->processConstantAnnotations($reflectionConstant, $constantAnnotations, $builder);
		}

		foreach ($reflectionClass->getMethods() as $reflectionMethod) {
			if (!$reflectionMethod->isStatic()) {
				$methodAnnotations = $annotationReader->getMethodAnnotations($reflectionMethod);
				$this->processMethodAnnotations($reflectionMethod, $methodAnnotations, $builder);
			}
		}

		foreach ($reflectionClass->getProperties() as $reflectionProperty) {
			if (!$reflectionProperty->isStatic()) {
				$propertyAnnotations = $annotationReader->getPropertyAnnotations($reflectionProperty);
				$this->processPropertyAnnotations($reflectionProperty, $propertyAnnotations, $builder);
			}
		}

	}

	private function processClassAnnotations(ReflectionClass $reflectionClass, array $annotations,
		StateMachineDefinitionBuilder $builder, bool $isSourceClass = true): void
	{
		$isStateMachine = false;

		foreach ($annotations as $annotation) {
			if ($annotation instanceof StateMachine) {
				$isStateMachine = true;
			}
			if ($annotation instanceof ReflectionClassAwareAnnotationInterface) {
				$annotation->setReflectionClass($reflectionClass);
			}
			if ($annotation instanceof ApplyToPlaceholderInterface) {
				$annotation->applyToPlaceholder($builder);
			}
			if ($annotation instanceof ApplyToStateMachineBuilderInterface) {
				$annotation->applyToBuilder($builder);
			}
			if ($annotation instanceof RecursiveAnnotationIncludeInterface) {
				foreach ($annotation->getIncludedClassNames() as $includedClassName) {
					$includedClass = new ReflectionClass($includedClassName);
					$this->processClassReflection($includedClass, $this->annotationReader, $builder, false);
				}
			}
		}

		if ($isSourceClass && !$isStateMachine) {
			throw new MissingStateMachineAnnotationException("No @StateMachine annotation found: " . $reflectionClass->getName());
		}
	}

	private function processConstantAnnotations(ReflectionClassConstant $reflectionConstant, array $annotations, StateMachineDefinitionBuilder $builder): void
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
					$placeholder = $builder->addState($stateName);
				}
			}
		}

		if (!$placeholder) {
			return; // This is not a state of the state machine.
		}

		// Apply all annotations to the state placeholder
		foreach ($annotations as $annotation) {
			if ($annotation instanceof ApplyToPlaceholderInterface) {
				$annotation->applyToPlaceholder($placeholder);
			}
			if ($annotation instanceof ApplyToStatePlaceholderInterface) {
				$annotation->applyToStatePlaceholder($placeholder);
			}
		}
	}

	private function processMethodAnnotations(ReflectionMethod $reflectionMethod, array $annotations, StateMachineDefinitionBuilder $builder): void
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
					$transitionPlaceholders[] = $builder->addTransition($transitionName, $annotation->source, $annotation->targets);
				} else {
					$actionPlaceholders[] = $builder->addAction($transitionName);
				}
			}
		}

		if (!$isTransition) {
			return; // This is not a transition.
		}

		foreach ($annotations as $annotation) {
			// Apply all annotations to both placeholders
			if ($annotation instanceof ApplyToPlaceholderInterface) {
				foreach ($actionPlaceholders as $placeholder) {
					$annotation->applyToPlaceholder($placeholder);
				}
				foreach ($transitionPlaceholders as $placeholder) {
					$annotation->applyToPlaceholder($placeholder);
				}
			}

			// Apply all annotations to the action placeholder
			if ($annotation instanceof ApplyToActionPlaceholderInterface) {
				foreach ($actionPlaceholders as $placeholder) {
					$annotation->applyToActionPlaceholder($placeholder);
				}
			}

			// Apply all annotations to the transition placeholders
			if ($annotation instanceof ApplyToTransitionPlaceholderInterface) {
				foreach ($transitionPlaceholders as $placeholder) {
					$annotation->applyToTransitionPlaceholder($placeholder);
				}
			}
		}
	}


	private function processPropertyAnnotations(ReflectionProperty $reflectionProperty, array $annotations, StateMachineDefinitionBuilder $builder): void
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
			$placeholder = $builder->addProperty($name, $type->getName(), $type->allowsNull());
		} else {
			$placeholder = $builder->addProperty($name);
		}

		foreach ($annotations as $annotation) {
			if ($annotation instanceof ApplyToPlaceholderInterface) {
				$annotation->applyToPlaceholder($placeholder);
			}
			if ($annotation instanceof ApplyToPropertyPlaceholderInterface) {
				$annotation->applyToPropertyPlaceholder($placeholder);
			}
		}
	}

}
