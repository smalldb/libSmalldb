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

use Smalldb\StateMachine\RuntimeException;
use Smalldb\StateMachine\Utils\PhpFileWriter;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Reference;
use Smalldb\StateMachine\ReferenceInterface;


class ReferenceClassGenerator
{
	private $outputDir;


	public function __construct(string $outputDir)
	{
		$this->outputDir = $outputDir;
	}


	/**
	 * Generate a new class implementing the missing methods in $sourceReferenceClassName.
	 *
	 * @return string Class name of the implementation.
	 */
	public function generateReferenceClass(string $sourceReferenceClassName, StateMachineDefinition $definition): string
	{
		try {
			$sourceClassReflection = new \ReflectionClass($sourceReferenceClassName);
			$hash = sha1(serialize([$sourceClassReflection, $definition]));

			$targetReferenceClassName = $sourceReferenceClassName . '__' . $hash;
			$shortTargetClassName = PhpFileWriter::getShortClassName($targetReferenceClassName);
			$targetFilename = $this->outputDir . '/' . $shortTargetClassName . '.php';

			if (!file_exists($targetFilename) || filemtime(__FILE__) > filemtime($targetFilename)) {
				$w = new PhpFileWriter();
				$w->setFileHeader(__CLASS__);
				$w->setNamespace($w->getClassNamespace($targetReferenceClassName));

				// Create the class
				$extends = $w->useClass(Reference::class);
				$implements = [$w->useClass(ReferenceInterface::class)];
				if ($sourceClassReflection->isInterface()) {
					$implements[] = $w->useClass($sourceReferenceClassName);
				} else {
					$extends = $w->useClass($sourceReferenceClassName);
				}
				$w->beginClass($shortTargetClassName, $extends, $implements);

				// Create methods
				$this->generateTransitionMethods($w, $definition, $sourceClassReflection);

				$w->endClass();
				$w->write($targetFilename);
			}
		}
		catch (\ReflectionException $ex) {
			throw new RuntimeException("Failed to generate Smalldb reference class: " . $definition->getMachineType(), 0, $ex);
		}

		if (!class_exists($targetReferenceClassName)) {
			require $targetFilename;
		}
		return $targetReferenceClassName;
	}


	/**
	 * @throws \ReflectionException
	 */
	private function getParamAsCode(\ReflectionParameter $param) : string
	{
		$code = '$' . $param->name;

		if ($param->isPassedByReference()) {
			$code = '& ' . $code;
		}

		if ($param->isVariadic()) {
			$code = '... ' . $code;
		}

		if (($type = $param->getType()) !== null) {
			$code = $type . ' ' . $code;

			if ($param->allowsNull()) {
				$code = '?' . $code;
			}
		}

		if ($param->isDefaultValueAvailable()) {
			if ($param->isDefaultValueConstant()) {
				$code .= ' = ' . $param->getDefaultValueConstantName();
			} else {
				$code .= ' = ' . var_export($param->getDefaultValue(), true);
			}
		}

		return $code;
	}


	/**
	 * @throws \ReflectionException
	 */
	private function generateTransitionMethods(PhpFileWriter $w, StateMachineDefinition $definition,
		\ReflectionClass $sourceClassReflection): void
	{
		foreach ($definition->getActions() as $action) {
			$methodName = $action->getName();
			if ($sourceClassReflection->hasMethod($methodName)) {
				$methodReflection = $sourceClassReflection->getMethod($methodName);
				if ($methodReflection->isAbstract()) {
					$argMethod = [];
					$argCall = [];
					foreach ($methodReflection->getParameters() as $param) {
						$argMethod[] = $this->getParamAsCode($param);
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

}
