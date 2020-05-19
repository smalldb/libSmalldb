<?php declare(strict_types = 1);
/*
 * Copyright (c) 2019-2020, Josef Kufner  <josef@kufner.cz>
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
use ReflectionNamedType;
use Smalldb\StateMachine\CodeGenerator\Annotation\InferredClass;
use Smalldb\StateMachine\Utils\AnnotationReader\AnnotationReaderInterface;
use Smalldb\StateMachine\Utils\PhpFileWriter;


class SmalldbEntityGenerator implements InferClassGenerator
{
	use GeneratorHelpers;


	public function __construct()
	{
	}


	public function processClass(ReflectionClass $sourceClass, InferClassAnnotation $annotation, AnnotationReaderInterface $annotationReader): void
	{
		$this->inferEntityClasses($sourceClass);
	}


	public function inferEntityClasses(ReflectionClass $sourceClass): array
	{
		$immutableInterface = $this->inferImmutableInterface($sourceClass, 'Interface');
		$gettersTrait = $this->inferGettersTrait($sourceClass, 'GettersTrait');
		$settersTrait = $this->inferSettersTrait($sourceClass, 'SettersTrait');
		$constructorTrait = $this->inferCopyConstructorTrait($sourceClass, 'CopyConstructorTrait', $immutableInterface);

		$immutableClass = $this->inferClass($sourceClass, 'Immutable', [$immutableInterface],
			[$constructorTrait, $gettersTrait]);
		$mutableClass = $this->inferClass($sourceClass, 'Mutable', [$immutableInterface],
			[$constructorTrait, $gettersTrait, $settersTrait]);

		return [
			$immutableInterface,
			$immutableClass,
			$mutableClass,
			$gettersTrait,
			$settersTrait,
			$constructorTrait,
		];
	}


	public function inferClass(ReflectionClass $sourceClass, string $suffix,
		array $interfaceNames = [], array $traitNames = []): string
	{
		return $this->writeClass($sourceClass, $suffix, function(PhpFileWriter $w, $targetNamespace, $targetShortName)
		use ($sourceClass, $interfaceNames, $traitNames)
		{
			$w->beginClass($targetShortName, $w->useClass($sourceClass->getName(), 'Source_' . $sourceClass->getShortName()), $w->useClasses($interfaceNames));

			if (!empty($traitNames)) {
				$w->writeln("use " . join(', ', $w->useClasses($traitNames)) . ";");
			}

			$w->endClass();
		});
	}


	public function inferImmutableInterface(ReflectionClass $sourceClass, string $suffix): string
	{
		return $this->writeClass($sourceClass, $suffix, function(PhpFileWriter $w, $targetNamespace, $targetShortName)
		use ($sourceClass)
		{
			$w->beginInterface($targetShortName);

			foreach ($sourceClass->getProperties() as $propertyReflection) {
				$propertyName = $propertyReflection->getName();
				$getterName = 'get' . ucfirst($propertyName);
				$typehint = $w->getTypeAsCode($propertyReflection->getType());

				$w->writeInterfaceMethod($getterName, [], $typehint);
			}

			$w->endInterface();
		});
	}


	public function inferGettersTrait(ReflectionClass $sourceClass, string $suffix): string
	{
		return $this->writeClass($sourceClass, $suffix, function(PhpFileWriter $w, $targetNamespace, $targetShortName)
		use ($sourceClass)
		{
			$w->beginTrait($targetShortName);

			foreach ($sourceClass->getProperties() as $propertyReflection) {
				$propertyName = $propertyReflection->getName();
				$getterName = 'get' . ucfirst($propertyName);
				$typehint = $w->getTypeAsCode($propertyReflection->getType());

				// Do not reimplement the existing getter
				if ($sourceClass->hasMethod($getterName)) {
					$sourceClassGetters = $sourceClass->getMethod($getterName);
					if (!$sourceClassGetters->isAbstract()) {
						continue;
					}
				}

				$w->beginMethod($getterName, [], $typehint);
				{
					if ($sourceClass->hasMethod($getterName) && !$sourceClass->getMethod($getterName)->isAbstract()) {
						// Do not reimplement the existing getter -- call it.
						$w->writeln("return parent::$getterName();");
					} else {
						// Get the property
						$w->writeln("return \$this->$propertyName;");
					}
				}
				$w->endMethod();
			}

			$w->endTrait();
		});
	}


	public function inferSettersTrait(ReflectionClass $sourceClass, string $suffix): string
	{
		return $this->writeClass($sourceClass, $suffix, function(PhpFileWriter $w, $targetNamespace, $targetShortName)
		use ($sourceClass)
		{
			$w->beginTrait($targetShortName);

			foreach ($sourceClass->getProperties() as $propertyReflection) {
				$propertyName = $propertyReflection->getName();
				$setterName = 'set' . ucfirst($propertyName);
				$param = $w->getParamCode($propertyReflection->getType(), $propertyReflection->getName());

				$w->beginMethod($setterName, [$param], 'void');
				{
					if ($sourceClass->hasMethod($setterName) && !$sourceClass->getMethod($setterName)->isAbstract()) {
						// Do not reimplement the existing setter -- call it.
						$w->writeln("parent::$setterName(\$$propertyName);");
					} else {
						// Set the property
						$w->writeln("\$this->$propertyName = \$$propertyName;");
					}
				}
				$w->endMethod();
			}

			$w->endTrait();
		});
	}


	public function inferCopyConstructorTrait(ReflectionClass $sourceClass, string $suffix,
		string $copyInterfaceName): string
	{
		return $this->writeClass($sourceClass, $suffix, function(PhpFileWriter $w, $targetNamespace, $targetShortName)
			use ($sourceClass, $copyInterfaceName)
		{
			$w->beginTrait($targetShortName);
			$w->beginMethod('__construct', [$w->useClass($copyInterfaceName) . ' $source = null']);

			// Call parent constructor if present
			if ($sourceClass->hasMethod('__construct')) {
				$constructorParameters = $sourceClass->getMethod('__construct')->getParameters();
				$firstParamType = $constructorParameters[0]->getType();
				$firstParamTypeName = $firstParamType instanceof ReflectionNamedType ? $firstParamType->getName() : null;

				if ($firstParamTypeName === $copyInterfaceName) {
					$w->writeln("parent::__construct(\$source);");
				} else {
					$w->writeln("parent::__construct();");
				}
			}

			$w->beginBlock('if ($source !== null)');
			{
				$w->beginBlock('if ($source instanceof ' . $w->useClass($sourceClass->getName()) . ')');
				{
					foreach ($sourceClass->getProperties() as $property) {
						$propertyName = $property->getName();
						$w->writeln("\$this->$propertyName = \$source->$propertyName;");
					}
				}
				$w->midBlock('else');
				{
					foreach ($sourceClass->getProperties() as $property) {
						$propertyName = $property->getName();
						$getterName = 'get' . ucfirst($propertyName);
						$w->writeln("\$this->$propertyName = \$source->$getterName();");
					}
				}
				$w->endBlock();
			}
			$w->endBlock();

			$w->endMethod();
			$w->endTrait();
		});
	}


	protected function writeClass(ReflectionClass $sourceClass, string $suffix, callable $writeCallback): string
	{
		$sourceShortName = $sourceClass->getShortName();
		$targetShortName = $sourceShortName . $suffix;
		$targetNamespace = $sourceClass->getName();
		$targetClassName = $targetNamespace . '\\' . $targetShortName;
		$targetDirectory = dirname($sourceClass->getFileName()) . DIRECTORY_SEPARATOR . $sourceShortName;
		$targetFilename = $targetDirectory . DIRECTORY_SEPARATOR . $targetShortName . '.php';

		$w = $this->createFileWriter($targetNamespace);
		$w->docComment("@" . $w->useClass(InferredClass::class) . "\n"
			. "@see \\" . $sourceClass->getName());

		$writeCallback($w, $targetNamespace, $targetShortName);

		if (!is_dir($targetDirectory)) {
			mkdir($targetDirectory);
		}
		$w->write($targetFilename);
		return $targetClassName;
	}

}

