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
use ReflectionException;
use ReflectionParameter;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Provider\SmalldbProviderInterface;
use Smalldb\StateMachine\ReferenceDataSource\ReferenceDataSourceInterface;
use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\ReferenceTrait;
use Smalldb\StateMachine\RuntimeException;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\Utils\PhpFileWriter;


class ReferenceClassGenerator extends AbstractClassGenerator
{

	/**
	 * Generate a new class implementing the missing methods in $sourceReferenceClassName.
	 *
	 * @return string Class name of the implementation.
	 */
	public function generateReferenceClass(string $sourceReferenceClassName, StateMachineDefinition $definition): string
	{
		try {
			$sourceClassReflection = new ReflectionClass($sourceReferenceClassName);

			$targetNamespace = $this->classGenerator->getClassNamespace();
			$shortTargetClassName = PhpFileWriter::getShortClassName($sourceReferenceClassName);
			$targetReferenceClassName = $targetNamespace . '\\' . $shortTargetClassName;

			$w = new PhpFileWriter();
			$w->setFileHeader(__CLASS__);
			$w->setNamespace($w->getClassNamespace($targetReferenceClassName));
			$w->setClassName($shortTargetClassName);

			// Create the class
			$extends = null;
			$implements = [$w->useClass(ReferenceInterface::class)];
			if ($sourceClassReflection->isInterface()) {
				$implements[] = $w->useClass($sourceReferenceClassName);
			} else {
				$extends = $w->useClass($sourceReferenceClassName);
			}
			$w->beginClass($shortTargetClassName, $extends, $implements);
			$w->writeln('use ' . $w->useClass(ReferenceTrait::class) . ';');

			// Create methods
			$this->generateReferenceMethods($w, $definition);
			$this->generateTransitionMethods($w, $definition, $sourceClassReflection);
			$this->generateDataGetterMethods($w, $sourceClassReflection);
			$this->generateHydrator($w, $sourceClassReflection);

			$w->endClass();
		}
		// @codeCoverageIgnoreStart
		catch (ReflectionException $ex) {
			throw new RuntimeException("Failed to generate Smalldb reference class: " . $definition->getMachineType(), 0, $ex);
		}
		// @codeCoverageIgnoreEnd

		$this->classGenerator->addGeneratedClass($targetReferenceClassName, $w->getPhpCode());
		return $targetReferenceClassName;
	}


	private function generateReferenceMethods(PhpFileWriter $w, StateMachineDefinition $definition): void
	{
		$w->beginMethod('getMachineType', [], 'string');
		$w->writeln('return %s;', $definition->getMachineType());
		$w->endMethod();
	}


	/**
	 * @throws ReflectionException
	 */
	private function generateTransitionMethods(PhpFileWriter $w, StateMachineDefinition $definition,
		ReflectionClass $sourceClassReflection): void
	{
		foreach ($definition->getActions() as $action) {
			$methodName = $action->getName();
			if ($sourceClassReflection->hasMethod($methodName)) {
				$methodReflection = $sourceClassReflection->getMethod($methodName);
				if ($methodReflection->isAbstract()) {
					$argMethod = [];
					$argCall = [];
					foreach ($methodReflection->getParameters() as $param) {
						$argMethod[] = $w->getParamAsCode($param);
						$argCall[] = '$' . $param->name;
					}
					$w->beginMethod($methodName, $argMethod);
					$argCallStr = empty($argCall) ? '' : ', ' . join(', ', $argCall);
					$w->writeln('return $this->invokeTransition(%s' . $argCallStr . ');', $methodName);
					$w->endMethod();
				}
			} else {
				$w->beginMethod($methodName, ['...$args']);
				$w->writeln('return $this->invokeTransition(%s, ...$args);', $methodName);
				$w->endMethod();
			}
		}
	}

	/**
	 * @throws ReflectionException
	 */
	private function generateDataGetterMethods(PhpFileWriter $w, ReflectionClass $sourceClassReflection)
	{
		$w->writeln("/** @var bool */");
		$w->writeln('private $dataLoaded = false;');

		$w->beginMethod('invalidateCache', [], 'void');
		{
			$w->writeln('$this->state = null;');
			$w->writeln('$this->dataLoaded = false;');
		}
		$w->endMethod();

		$w->beginProtectedMethod('loadData', [], 'void');
		{
			$w->writeln("\$data = \$this->dataSource->loadData(\$this->getId(), \$this->state);");
			$w->beginBlock("if (\$data !== null)");
			{
				$w->writeln("if (is_array(\$data)) {");
				$w->writeln("\tstatic::hydrate(\$this, \$data);");
				$w->writeln("} else {");
				$w->writeln("\t\$this->copyProperties(\$data);");
				$w->writeln("}");
			}
			$w->writeln("\$this->dataLoaded = true;");
			$w->endBlock();
		}
		$w->endMethod();

		$w->beginProtectedMethod('onDataPreloaded', [], 'void');
		{
			$w->writeln('$this->dataLoaded = true;');
		}
		$w->endMethod();

		$referenceInterfaceReflection = new ReflectionClass(ReferenceInterface::class);

		foreach ($sourceClassReflection->getMethods() as $method) {
			$methodName = $method->getName();
			if (strncmp('get', $methodName, 3) === 0 && !$w->hasMethod($methodName) && !$referenceInterfaceReflection->hasMethod($methodName))
			{
				$argMethod = [];
				$argCall = [];
				foreach ($method->getParameters() as $param) {
					$argMethod[] = $w->getParamAsCode($param);
					$argCall[] = '$' . $param->name;
				}
				$returnType = (string) $method->getReturnType();
				if (class_exists($returnType)) {
					$returnType = $w->useClass($returnType);
				}

				$w->beginMethod($methodName, $argMethod, $returnType);
				$w->beginBlock("if (!\$this->dataLoaded)");
				{
					$w->writeln("\$this->loadData();");
				}
				$w->endBlock();
				$w->writeln("return parent::$methodName(" . join(', ', $argCall) . ");");
				$w->endMethod();
			}
		}
	}

	private function generateHydrator(PhpFileWriter $w, ReflectionClass $sourceClassReflection): void
	{
		$w->beginStaticMethod('hydrate', ['self $target', 'array $row'], 'void');
		{
			foreach ($sourceClassReflection->getProperties() as $property) {
				$name = $property->getName();
				$w->writeln("\$target->$name = \$row[%s];", $name);
			}
			$w->writeln("\$target->dataLoaded = true;");
		}
		$w->endMethod();


		$args = [
			$w->useClass(Smalldb::class) . ' $smalldb',
			'?' . $w->useClass(SmalldbProviderInterface::class) . ' $machineProvider',
			$w->useClass(ReferenceDataSourceInterface::class) . ' $dataSource'
		];
		$useArgs = ['$smalldb', '$machineProvider', '$dataSource'];

		$w->beginStaticMethod('createFetchHydrator', $args, $w->useClass(\Closure::class));
		{
			$closureArgs = [];
			foreach ($sourceClassReflection->getProperties() as $property) {
				$closureArgs[] = '$' . $property->getName();
			}

			$w->beginBlock('return function (' . join(', ', $closureArgs) . ') use (' . join(', ', $useArgs) . '): self');
			{
				$w->writeln("\$target = new self(\$smalldb, \$machineProvider, \$dataSource);");
				foreach ($sourceClassReflection->getProperties() as $property) {
					$name = $property->getName();
					$w->writeln("\$target->$name = \$$name;");
				}
				$w->writeln("\$target->dataLoaded = true;");
				$w->writeln("return \$target;");
			}
			$w->endBlock(';');
		}
		$w->endMethod();
	}

}
