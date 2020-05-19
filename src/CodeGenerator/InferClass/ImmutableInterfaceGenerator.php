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

use ReflectionClass;
use ReflectionMethod;
use Smalldb\StateMachine\Utils\AnnotationReader\AnnotationReaderInterface;
use Smalldb\StateMachine\Utils\AnnotationReader\TypeResolver;
use Smalldb\StateMachine\Utils\PhpFileWriter;


class ImmutableInterfaceGenerator extends AbstractInferClassGenerator implements InferClassGenerator
{

	public function processClass(ReflectionClass $sourceClass, InferClassAnnotation $annotation,
		AnnotationReaderInterface $annotationReader): void
	{
		$targetNamespace = $sourceClass->getName();
		$typeResolver = new TypeResolver($sourceClass);
		$targetShortClassName = $sourceClass->getShortName() . 'Immutable';
		$targetDir = $this->getTargetDirectory($sourceClass);

		$this->generateImmutableInterface($sourceClass, $annotation, $annotationReader, $typeResolver,
			$targetNamespace, $targetShortClassName, $targetDir);
	}


	private function generateImmutableInterface(ReflectionClass $sourceClass, InferClassAnnotation $annotation,
		AnnotationReaderInterface $annotationReader, TypeResolver $typeResolver,
		string $targetNamespace, string $targetShortClassName, string $targetDir)
	{
		$w = $this->createFileWriter($targetNamespace, $annotation);
		$w->beginInterface($targetShortClassName);

		foreach ($sourceClass->getMethods() as $methodReflection) {
			if (strncmp($methodReflection->getName(), 'get', 3) === 0 && $methodReflection->isPublic()) {
				$this->inferInterfaceMethod($w, $methodReflection);
			}
		}

		$w->endInterface();
		$w->write($targetDir . DIRECTORY_SEPARATOR . $targetShortClassName . '.php');
	}


	private function inferInterfaceMethod(PhpFileWriter $w, ReflectionMethod $methodReflection)
	{
		$methodName = $methodReflection->getName();

		$argMethod = [];
		foreach ($methodReflection->getParameters() as $param) {
			$argMethod[] = $w->getParamAsCode($param);
		}

		$returnType = $w->getTypeAsCode($methodReflection->getReturnType());

		$w->writeInterfaceMethod($methodName, $argMethod, $returnType);
	}

}
