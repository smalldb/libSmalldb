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

namespace Smalldb\StateMachine\ClassGenerator\ReferenceClassGenerator;

use ReflectionClass;
use Smalldb\StateMachine\ClassGenerator\AbstractClassGenerator;
use Smalldb\StateMachine\ClassGenerator\ReflectionException;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\ReferenceTrait;
use Smalldb\PhpFileWriter\PhpFileWriter;


abstract class AbstractGenerator extends AbstractClassGenerator
{

	final public function generateReferenceClass(string $sourceReferenceClassName, StateMachineDefinition $definition): string
	{
		try {
			$sourceClassReflection = new ReflectionClass($sourceReferenceClassName);
			$w = $this->createPhpFileWriter($sourceClassReflection);

			$targetReferenceClassName = $this->writeReferenceClass($w, $sourceClassReflection, $definition);

			$this->getClassGenerator()->addGeneratedClass($targetReferenceClassName, $w->getPhpCode());
			return $targetReferenceClassName;
		}
		// @codeCoverageIgnoreStart
		catch (\ReflectionException $ex) {
			throw new ReflectionException("Failed to generate Smalldb reference class: " . $definition->getMachineType(), 0, $ex);
		}
		// @codeCoverageIgnoreEnd
	}


	abstract protected function writeReferenceClass(PhpFileWriter $w, ReflectionClass $sourceClassReflection, StateMachineDefinition $definition): string;


	protected function createPhpFileWriter(ReflectionClass $sourceClassReflection): PhpFileWriter
	{
		$targetNamespace = $this->getClassGenerator()->getClassNamespace();
		$targetShortClassName = $sourceClassReflection->getShortName();

		// Setup the writer
		$w = new PhpFileWriter();
		$w->setFileHeader(get_class($this));
		$w->setNamespace($targetNamespace);
		$w->setClassName($targetShortClassName);

		return $w;
	}


	protected function beginReferenceClass(PhpFileWriter $w, ReflectionClass $sourceClassReflection, array $implements = []): string
	{
		$targetNamespace = $this->getClassGenerator()->getClassNamespace();
		$targetShortClassName = strtr($sourceClassReflection->getName(), ['\\' =>'__']);
		$targetReferenceClassName = $targetNamespace . '\\' . $targetShortClassName;

		// Add parent class/interface
		if ($sourceClassReflection->isInterface()) {
			$extends = null;
			$implements[] = $w->useClass($sourceClassReflection->getName());
		} else {
			$extends = $w->useClass($sourceClassReflection->getName());
			$implements[] = $w->useClass(ReferenceInterface::class);
		}

		// Create the class
		$w->beginClass($targetShortClassName, $extends, $implements);
		$w->writeln('use ' . $w->useClass(ReferenceTrait::class) . ';');

		return $targetReferenceClassName;
	}


	protected function generateTransitionMethods(PhpFileWriter $w, StateMachineDefinition $definition,
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


	protected function generateReferenceMethods(PhpFileWriter $w, StateMachineDefinition $definition): void
	{
		$w->beginMethod('getMachineType', [], 'string');
		$w->writeln('return %s;', $definition->getMachineType());
		$w->endMethod();
	}


	protected function generateIdMethods(PhpFileWriter $w)
	{
		$w->writeln('private $machineId = null;');

		$w->beginMethod('getMachineId', [], '');
		{
			$w->writeln('return $this->machineId;');
		}
		$w->endMethod();

		$w->beginProtectedMethod('setMachineId', ['$machineId'], 'void');
		{
			$w->writeln('$this->machineId = $machineId;');
		}
		$w->endMethod();
	}


	protected function generateFallbackExistsStateFunction(PhpFileWriter $w, ReflectionClass $sourceClassReflection,
		StateMachineDefinition $definition, string $canLoadDataCondition)
	{
		if (!$w->hasMethod('getState') && ($stateMethod = $sourceClassReflection->getMethod('getState')) && $stateMethod->isAbstract()) {
			$states = $definition->getStates();
			if (count($states) === 2) {
				// There are two states: NOT_EXISTS and EXISTS. If there are any data, it EXISTS.
				$theOtherState = null;
				foreach ($states as $state) {
					if ($state->getName() !== ReferenceInterface::NOT_EXISTS) {
						$theOtherState = $state->getName();
						break;
					}
				}

				$w->beginMethod('getState', [], 'string');
				{
					$w->writeln("return ($canLoadDataCondition) ? %s : " . $w->useClass(ReferenceInterface::class) . "::NOT_EXISTS;", $theOtherState);
				}
				$w->endMethod();
			} else {
				$w->beginMethod('getState', [], 'string');
				{
					$w->writeln("return " . $w->useClass(ReferenceInterface::class) . "::NOT_EXISTS;");
				}
				$w->endMethod();
			}
		}
	}

}
