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
use ReflectionProperty;
use Smalldb\StateMachine\Utils\ExtendedReflectionClass;
use Smalldb\StateMachine\Utils\PhpFileWriter;


class SmalldbEntityGenerator implements InferClassGenerator
{

	/** @var TypeResolver */
	private $typeResolver;


	public function processClass(ReflectionClass $sourceClass, InferClassAnnotation $annotation): void
	{
		$targetNamespace = $sourceClass->getName();
		$targetDir = dirname($sourceClass->getFileName()) . DIRECTORY_SEPARATOR . $sourceClass->getShortName();
		if (!is_dir($targetDir)) {
			mkdir($targetDir);
		}

		$this->typeResolver = new TypeResolver($sourceClass);

		$immutableInterfaceName = $this->generateImmutableInterface($sourceClass, $annotation, $targetNamespace, $targetDir);
		$immutableClassName = $this->generateImmutableClass($sourceClass, $annotation, $targetNamespace, $targetDir, $immutableInterfaceName);
		$mutableClassName = $this->generateMutableClass($sourceClass, $annotation, $targetNamespace, $targetDir, $immutableClassName);
	}


	private function generateImmutableInterface(ReflectionClass $sourceClass, InferClassAnnotation $annotation, string $targetNamespace, string $targetDir)
	{
		$annotationReflection = new ReflectionClass($annotation);

		$targetShortClassName = $sourceClass->getShortName() . 'ImmutableInterface';
		$targetClassName = $targetNamespace . '\\' . $targetShortClassName;

		$w = new PhpFileWriter();
		$w->setFileHeader(__CLASS__ . ' (@' . $annotationReflection->getShortName() . ' annotation)');
		$w->setNamespace($targetNamespace);
		$w->beginInterface($targetShortClassName);

		foreach ($sourceClass->getProperties() as $propertyReflection) {
			$propertyName = $propertyReflection->getName();
			$getterName = 'get' . ucfirst($propertyName);
			$type = $this->typeResolver->getPropertyType($propertyReflection);
			if ($propertyName == 'id') {
				$w->docComment("@return " . $type);
				$type = '';
			}
			$w->writeInterfaceMethod($getterName, [], $w->useClass($type));
		}

		$w->endClass();
		$w->write($targetDir . DIRECTORY_SEPARATOR . $targetShortClassName . '.php');
		return $targetClassName;
	}


	private function generateImmutableClass(ReflectionClass $sourceClass, InferClassAnnotation $annotation, string $targetNamespace, string $targetDir, string $immutableInterfaceName)
	{
		$annotationReflection = new ReflectionClass($annotation);

		$targetShortClassName = $sourceClass->getShortName() . 'Immutable';
		$targetClassName = $targetNamespace . '\\' . $targetShortClassName;

		$w = new PhpFileWriter();
		$w->setFileHeader(__CLASS__ . ' (@' . $annotationReflection->getShortName() . ' annotation)');
		$w->setNamespace($targetNamespace);
		$w->beginClass($targetShortClassName, $w->useClass($sourceClass->getName()), [$w->useClass($immutableInterfaceName)]);

		foreach ($sourceClass->getProperties() as $propertyReflection) {
			$propertyName = $propertyReflection->getName();
			$getterName = 'get' . ucfirst($propertyName);
			$type = $this->typeResolver->getPropertyType($propertyReflection);

			$w->beginMethod($getterName, [], $w->useClass($type));
			{
				$w->writeln("return \$this->$propertyName;");
			}
			$w->endMethod();
		}

		$w->endClass();
		$w->write($targetDir . DIRECTORY_SEPARATOR . $targetShortClassName . '.php');
		return $targetClassName;
	}


	private function generateMutableClass(ReflectionClass $sourceClass, InferClassAnnotation $annotation, string $targetNamespace, string $targetDir, string $immutableClassName)
	{
		$annotationReflection = new ReflectionClass($annotation);

		$targetShortClassName = $sourceClass->getShortName() . 'Mutable';
		$targetClassName = $targetNamespace . '\\' . $targetShortClassName;

		$w = new PhpFileWriter();
		$w->setFileHeader(__CLASS__ . ' (@' . $annotationReflection->getShortName() . ' annotation)');
		$w->setNamespace($targetNamespace);
		$w->beginClass($targetShortClassName, $w->useClass($immutableClassName));

		foreach ($sourceClass->getProperties() as $propertyReflection) {
			$propertyName = $propertyReflection->getName();
			$setterName = 'set' . ucfirst($propertyName);
			$type = $this->typeResolver->getPropertyType($propertyReflection);

			if ($type) {
				$arg = $w->useClass($type) . ' $' . $propertyName;
			} else {
				$arg = '$' . $propertyName;
			}

			$w->beginMethod($setterName, [$arg], 'void');
			{
				$w->writeln("\$this->$propertyName = \$$propertyName;");
			}
			$w->endMethod();
		}

		$w->endClass();
		$w->write($targetDir . DIRECTORY_SEPARATOR . $targetShortClassName . '.php');
		return $targetClassName;
	}


}
