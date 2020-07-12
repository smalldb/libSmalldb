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

namespace Smalldb\CodeCooker\Generator;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Smalldb\CodeCooker\Annotation\GeneratedClass;
use Smalldb\CodeCooker\Annotation\PublicMutator;
use Smalldb\StateMachine\Utils\AnnotationReader\AnnotationReader;
use Smalldb\StateMachine\Utils\AnnotationReader\AnnotationReaderInterface;
use Smalldb\ClassLocator\ClassLocator;
use Smalldb\PhpFileWriter\PhpFileWriter;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\OptionsResolver\OptionsResolver;


class DtoGenerator
{
	use GeneratorHelpers;

	private ClassLocator $classLocator;
	private AnnotationReaderInterface $annotationReader;


	public function __construct(ClassLocator $classLocator, ?AnnotationReaderInterface $annotationReader = null)
	{
		$this->classLocator = $classLocator;
		$this->annotationReader = $annotationReader ?? (new AnnotationReader());
	}


	public static function calculateTargetClassNames(ReflectionClass $sourceClass, ?string $targetName = null): array
	{
		$suffixes = [
			'',
			'Immutable',
			'Mutable',
			'FormDataMapper',
		];

		$sourceShortName = $sourceClass->getShortName();
		$targetName ??= $sourceShortName;
		$targetNamespace = $sourceClass->getNamespaceName() . '\\' . $targetName;

		$targetClassNames = [];
		foreach ($suffixes as $suffix) {
			$targetShortName = $targetName . $suffix;
			$targetClassNames[] = $targetNamespace . '\\' . $targetShortName;
		}
		return $targetClassNames;
	}


	public function generateDtoClasses(ReflectionClass $sourceClass, ?string $targetName = null): array
	{
		$immutableInterface = $this->inferImmutableInterface($sourceClass, $targetName, '');
		$immutableClass = $this->inferImmutableClass($sourceClass, $targetName, 'Immutable', $immutableInterface);
		$mutableClass = $this->inferMutableClass($sourceClass, $targetName, 'Mutable', $immutableInterface);
		$formDataMapper = $this->inferFormDataMapper($sourceClass, $targetName, 'FormDataMapper', $immutableInterface, $immutableClass);

		return [
			$immutableInterface,
			$immutableClass,
			$mutableClass,
			$formDataMapper,
		];
	}


	private function inferImmutableClass(ReflectionClass $sourceClass, ?string $targetName, string $suffix, string $immutableInterfaceName): string
	{
		return $this->writeClass($sourceClass, $targetName, $suffix, function (PhpFileWriter $w, $targetNamespace, $targetShortName)
			use ($sourceClass, $immutableInterfaceName)
		{
			$w->beginClass($targetShortName, $w->useClass($sourceClass->getName(), 'Source_' . $sourceClass->getShortName()), [$w->useClass($immutableInterfaceName)]);
			$this->generateConstructors($w, $sourceClass, $immutableInterfaceName);
			$this->generateGetters($w, $sourceClass, $immutableInterfaceName);
			$this->generateWithers($w, $sourceClass);
			$w->endClass();
		});
	}


	private function inferMutableClass(ReflectionClass $sourceClass, ?string $targetName, string $suffix, string $immutableInterfaceName): string
	{
		return $this->writeClass($sourceClass, $targetName, $suffix, function (PhpFileWriter $w, $targetNamespace, $targetShortName)
			use ($sourceClass, $immutableInterfaceName)
		{
			$w->beginClass($targetShortName, $w->useClass($sourceClass->getName(), 'Source_' . $sourceClass->getShortName()), [$w->useClass($immutableInterfaceName)]);
			$this->generateConstructors($w, $sourceClass, $immutableInterfaceName);
			$this->generateGetters($w, $sourceClass, $immutableInterfaceName);
			$this->generateSetters($w, $sourceClass);
			$w->endClass();
		});
	}


	private function inferImmutableInterface(ReflectionClass $sourceClass, ?string $targetName, string $suffix): string
	{
		return $this->writeClass($sourceClass, $targetName, $suffix, function (PhpFileWriter $w, $targetNamespace, $targetShortName)
			use ($sourceClass)
		{
			$w->beginInterface($targetShortName);

			foreach ($sourceClass->getProperties() as $propertyReflection) {
				$propertyName = $propertyReflection->getName();
				$getterName = 'get' . ucfirst($propertyName);
				$typehint = $w->getTypeAsCode($propertyReflection->getType());

				$w->writeInterfaceMethod($getterName, [], $typehint);
			}

			foreach ($sourceClass->getMethods() as $methodReflection) {
				$methodName = $methodReflection->getName();
				if ($methodName !== '__construct' && $methodReflection->isPublic()) {
					[$argMethod, $argCall] = $w->getMethodParametersCode($methodReflection);
					$w->writeInterfaceMethod($methodName, $argMethod,
						$w->getTypeAsCode($methodReflection->getReturnType()));
				}
			}

			$w->endInterface();
		});
	}


	protected function inferFormDataMapper(ReflectionClass $sourceClass, ?string $targetName, string $suffix, string $immutableInterfaceName, string $immutableClassName)
	{
		return $this->writeClass($sourceClass, $targetName, $suffix, function (PhpFileWriter $w, $targetNamespace, $targetShortName)
			use ($sourceClass, $immutableInterfaceName, $immutableClassName)
		{
			$w->beginClass($targetShortName, null, [$w->useClass(DataMapperInterface::class)]);

			$w->beginMethod('mapDataToForms', ['$viewData', 'iterable $forms']);
			{
				$w->beginBlock('if ($viewData === null)');
				{
					$w->writeln('return;');
				}
				$w->midBlock('else if ($viewData instanceof ' . $w->useClass($immutableInterfaceName) . ')');
				{
					$w->beginBlock('foreach ($forms as $prop => $field)');
					{
						$w->writeln('$field->setData(' . $w->useClass($immutableClassName) . '::get($viewData, $prop));');
					}
					$w->endBlock();
				}
				$w->midBlock('else');
				{
					$w->writeln('throw new ' . $w->useClass(UnexpectedTypeException::class) . '($viewData, ' . $w->useClass($immutableClassName) . '::class);');
				}
				$w->endBlock();
			}
			$w->endMethod();

			$w->beginMethod('mapFormsToData', ['iterable $forms', '& $viewData']);
			{
				$w->writeln('$viewData = ' . $w->useClass($immutableClassName) . '::fromIterable($viewData, $forms, function ($field) { return $field->getData(); });');
			}
			$w->endMethod();

			$w->beginMethod('configureOptions', [$w->useClass(OptionsResolver::class) . ' $optionsResolver']);
			{
				$w->writeln('$optionsResolver->setDefault("empty_data", null);');
				$w->writeln('$optionsResolver->setDefault("data_class", ' . $w->useClass($immutableInterfaceName) . '::class);');
			}
			$w->endMethod();

			$w->endClass();
		});
	}


	private function hasPublicMutatorAnnotation(ReflectionMethod $methodReflection)
	{
		$methodAnnotations = $this->annotationReader->getMethodAnnotations($methodReflection);
		foreach ($methodAnnotations as $annotation) {
			if ($annotation instanceof PublicMutator) {
				return true;
			}
		}
		return false;
	}


	protected function writeClass(ReflectionClass $sourceClass, ?string $targetName, string $suffix, callable $writeCallback): string
	{
		$sourceShortName = $sourceClass->getShortName();
		$targetName ??= $sourceShortName;

		$targetShortName = $targetName . $suffix;
		$targetNamespace = $sourceClass->getNamespaceName() . '\\' . $targetName;
		$targetClassName = $targetNamespace . '\\' . $targetShortName;

		$targetFilename = $this->classLocator->mapClassNameToFileName($targetClassName);
		$targetDirectory = dirname($targetFilename);

		$w = $this->createFileWriter($targetNamespace);
		$w->docComment("@" . $w->useClass(GeneratedClass::class) . "\n"
			. "@see \\" . $sourceClass->getName());

		$writeCallback($w, $targetNamespace, $targetShortName);

		if (!is_dir($targetDirectory)) {
			mkdir($targetDirectory);
		}
		$w->write($targetFilename);
		return $targetClassName;
	}


	private function generateGetters(PhpFileWriter $w, ReflectionClass $sourceClass, string $immutableInterface): void
	{
		foreach ($sourceClass->getProperties() as $propertyReflection) {
			$propertyName = $propertyReflection->getName();
			$typehint = $w->getTypeAsCode($propertyReflection->getType());

			$getterName = 'get' . ucfirst($propertyName);
			$getter = $sourceClass->hasMethod($getterName) ? $sourceClass->getMethod($getterName) : null;

			$w->beginMethod($getterName, [], $typehint);
			{
				if ($getter && !$getter->isAbstract()) {
					// Do not reimplement the existing getter -- call it.
					$w->writeln("return parent::$getterName();");
				} else {
					// Get the property
					$w->writeln("return \$this->$propertyName;");
				}
			}
			$w->endMethod();
		}

		$w->beginStaticMethod('get', [$w->useClass($immutableInterface) . ' $source', 'string $propertyName']);
		{
			$w->beginBlock('switch ($propertyName)');
			{
				foreach ($sourceClass->getProperties() as $propertyReflection) {
					$propertyName = $propertyReflection->getName();
					$getterName = 'get' . ucfirst($propertyName);
					$w->writeln("case %s: return \$source->$getterName();", $propertyName);
				}
				$w->writeln('default: throw new \InvalidArgumentException("Unknown property: " . $propertyName);');
			}
			$w->endBlock();
		}
		$w->endMethod();

	}


	private function generateSetters(PhpFileWriter $w, ReflectionClass $sourceClass): void
	{
		foreach ($sourceClass->getProperties() as $propertyReflection) {
			$propertyName = $propertyReflection->getName();
			$param = $w->getParamCode($propertyReflection->getType(), $propertyReflection->getName());

			$setterName = 'set' . ucfirst($propertyName);
			$sourceSetter = $sourceClass->hasMethod($setterName) ? $sourceClass->getMethod($setterName) : null;

			$w->beginMethod($setterName, [$param], 'void');
			{
				if ($sourceSetter && $sourceSetter->isAbstract()) {
					// Do not reimplement the existing setter -- call it.
					$w->writeln("parent::$setterName(\$$propertyName);");
				} else {
					// Set the property
					$w->writeln("\$this->$propertyName = \$$propertyName;");
				}
			}
			$w->endMethod();
		}

		// Export annotated mutators
		foreach ($sourceClass->getMethods() as $methodReflection) {
			if ($this->hasPublicMutatorAnnotation($methodReflection)) {
				$methodName = $methodReflection->getName();
				[$argMethod, $argCall] = $w->getMethodParametersCode($methodReflection);
				$returnTypehint = $w->getTypeAsCode($methodReflection->getReturnType());
				$w->beginMethod($methodName, $argMethod, $returnTypehint);
				{
					$parentCall = "parent::$methodName(" . join(', ', $argCall) . ");";
					$w->writeln($returnTypehint !== 'void' ? "return " . $parentCall : $parentCall);
				}
				$w->endMethod();
			}
		}
	}


	private function generateWithers(PhpFileWriter $w, ReflectionClass $sourceClass): void
	{
		foreach ($sourceClass->getProperties() as $propertyReflection) {
			$propertyName = $propertyReflection->getName();
			$param = $w->getParamCode($propertyReflection->getType(), $propertyReflection->getName());

			$setterName = 'set' . ucfirst($propertyName);
			$witherName = 'with' . ucfirst($propertyName);
			$hasSourceSetter = $sourceClass->hasMethod($setterName);

			$w->beginMethod($witherName, [$param], 'self');
			{
				$w->writeln("\$t = clone \$this;");
				if ($hasSourceSetter) {
					// Do not reimplement the existing setter -- call it.
					$w->writeln("\$t->$setterName(\$$propertyName);");
				} else {
					// Set the property
					$w->writeln("\$t->$propertyName = \$$propertyName;");
				}
				$w->writeln("return \$t;");
			}
			$w->endMethod();
		}

		// Export annotated mutators
		foreach ($sourceClass->getMethods() as $methodReflection) {
			if ($this->hasPublicMutatorAnnotation($methodReflection)) {
				$methodName = $methodReflection->getName();
				$witherName = strncmp($methodName, 'set', 3) === 0 ? 'with' . substr($methodName, 3) : 'with' . ucfirst($methodName);
				[$argMethod, $argCall] = $w->getMethodParametersCode($methodReflection);
				$w->beginMethod($witherName, $argMethod, 'self');
				{
					$w->writeln("\$t = clone \$this;");
					$w->writeln("\$t->$methodName(" . join(', ', $argCall) . ");");
					$w->writeln("return \$t;");
				}
				$w->endMethod();
			}
		}
	}


	private function generateConstructors(PhpFileWriter $w, ReflectionClass $sourceClass, string $copyInterfaceName): void
	{
		$sourceConstructor = $sourceClass->hasMethod('__construct') ? $sourceClass->getMethod('__construct') : null;

		$w->beginMethod('__construct', ['?' . $w->useClass($copyInterfaceName) . ' $source = null']);
		{

			// Call parent constructor if present
			if ($sourceConstructor) {
				$constructorParameters = $sourceConstructor->getParameters();
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
		}
		$w->endMethod();

		$w->beginStaticMethod('fromArray', ['?array $source', '?' . $w->useClass($copyInterfaceName) . ' $sourceObj = null'], '?self');
		{
			$w->beginBlock("if (\$source === null)");
			{
				$w->writeln("return null;");
			}
			$w->endBlock();

			$w->writeln("\$t = \$sourceObj instanceof self ? clone \$sourceObj : new self(\$sourceObj);");
			foreach ($sourceClass->getProperties() as $property) {
				$propertyName = $property->getName();
				$type = $property->getType();
				if ($type instanceof ReflectionNamedType) {
					$typehint = $type->getName();
				} else {
					$typehint = null;
				}

				//$w->writeln("\$t->$propertyName = \$source['$propertyName'];");

				// Convert value to fit the typehint
				switch ($typehint) {
					case 'int':
					case 'float':
					case 'bool':
					case 'string':
						if ($type->allowsNull()) {
							$w->writeln("\$t->$propertyName = isset(\$source[%s]) ? ($typehint) \$source[%s] : null;", $propertyName, $propertyName);
						} else {
							$w->writeln("\$t->$propertyName = ($typehint) \$source[%s];", $propertyName, $propertyName);
						}
						break;

					default:
						if ($typehint && class_exists($typehint)) {
							$c = $w->useClass($typehint);
							$w->writeln("\$t->$propertyName = (\$v = \$source[%s] ?? null) instanceof $c || \$v === null ? \$v : new $c(\$v);", $propertyName);
						} else {
							$w->writeln("\$t->$propertyName = \$source[%s] ?? null;", $propertyName);
						}
						break;
				}
			}
			$w->writeln("return \$t;");
		}
		$w->endMethod();

		$w->beginStaticMethod('fromIterable', ['?' . $w->useClass($copyInterfaceName) . ' $sourceObj', 'iterable $source', '?callable $mapFunction = null'], 'self');
		{
			$w->writeln("\$t = \$sourceObj instanceof self ? clone \$sourceObj : new self(\$sourceObj);");
			$w->beginBlock("foreach (\$source as \$prop => \$value)");
			{
				$w->beginBlock("switch (\$prop)");
				{
					foreach ($sourceClass->getProperties() as $property) {
						$propertyName = $property->getName();
						$w->writeln("case '$propertyName': \$t->$propertyName = \$mapFunction ? \$mapFunction(\$value) : \$value; break;");
					}
					$w->writeln("default: throw new " . $w->useClass(InvalidArgumentException::class) . "('Unknown property: \"' . \$prop . '\" not in ' . __CLASS__);");
				}
				$w->endBlock();
			}
			$w->endBlock();
			$w->writeln("return \$t;");
		}
		$w->endMethod();
	}

}

