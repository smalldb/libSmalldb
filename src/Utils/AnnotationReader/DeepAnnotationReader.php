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

namespace Smalldb\StateMachine\Utils\AnnotationReader;

use ReflectionClass;
use ReflectionClassConstant;
use ReflectionMethod;
use ReflectionProperty;


class DeepAnnotationReader extends AnnotationReader implements AnnotationReaderInterface
{

	public function getClassAnnotations(ReflectionClass $class)
	{
		$annotations = [parent::getClassAnnotations($class)];
		while (($class = $class->getParentClass())) {
			$annotations[] = parent::getClassAnnotations($class);
		}
		return array_merge(...array_reverse($annotations));
	}


	public function getPropertyAnnotations(ReflectionProperty $property)
	{
		$annotations = [parent::getPropertyAnnotations($property)];
		$class = $property->getDeclaringClass();
		$propertyName = $property->getName();
		while (($class = $class->getParentClass()) !== false && $class->hasProperty($propertyName)) {
			$property = $class->getProperty($propertyName);
			$annotations[] = parent::getPropertyAnnotations($property);
		}
		return array_merge(...array_reverse($annotations));
	}


	public function getMethodAnnotations(ReflectionMethod $method)
	{
		$annotations = [parent::getMethodAnnotations($method)];
		$class = $method->getDeclaringClass();
		$methodName = $method->getName();
		while (($class = $class->getParentClass()) && $class->hasMethod($methodName)) {
			$method = $class->getMethod($methodName);
			$annotations[] = parent::getMethodAnnotations($method);
		}
		return array_merge(...array_reverse($annotations));
	}


	public function getConstantAnnotations(ReflectionClassConstant $constant): array
	{
		$annotations = [parent::getConstantAnnotations($constant)];
		$class = $constant->getDeclaringClass();
		$constantName = $constant->getName();
		while (($class = $class->getParentClass()) && $class->hasConstant($constantName)) {
			$constant = $class->getReflectionConstant($constantName);
			$annotations[] = parent::getConstantAnnotations($constant);
		}
		return array_merge(...array_reverse($annotations));
	}

}
