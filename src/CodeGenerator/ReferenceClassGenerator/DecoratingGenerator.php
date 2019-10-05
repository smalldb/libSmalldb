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

namespace Smalldb\StateMachine\CodeGenerator\ReferenceClassGenerator;

use ReflectionClass;
use Smalldb\StateMachine\CodeGenerator\LogicException;
use Smalldb\StateMachine\CodeGenerator\ReflectionException;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\InvalidArgumentException;
use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\ReferenceTrait;
use Smalldb\StateMachine\Utils\PhpFileWriter;


class DecoratingGenerator extends AbstractGenerator
{

	/**
	 * Generate a new class implementing the missing methods in $sourceReferenceClassName.
	 *
	 * @return string Class name of the implementation.
	 * @throws ReflectionException
	 * @throws LogicException
	 */
	public function generateReferenceClass(string $sourceReferenceClassName, StateMachineDefinition $definition): string
	{
		try {
			$sourceClassReflection = new ReflectionClass($sourceReferenceClassName);

			$targetNamespace = $this->getClassGenerator()->getClassNamespace();
			$shortTargetClassName = PhpFileWriter::getShortClassName($sourceReferenceClassName);
			$targetReferenceClassName = $targetNamespace . '\\' . $shortTargetClassName;

			$w = new PhpFileWriter();
			$w->setFileHeader(__CLASS__);
			$w->setNamespace($w->getClassNamespace($targetReferenceClassName));
			$w->setClassName($shortTargetClassName);

			// Detect DTO interface
			$dtoInterface = null;
			foreach ($sourceClassReflection->getInterfaces() as $interface) {
				if (!$interface->implementsInterface(ReferenceInterface::class)) {
					if ($dtoInterface) {
						// TODO: This may not be that bad. We may support multiple DTOs.
						throw new InvalidArgumentException("Multiple DTO interfaces found in " . $sourceReferenceClassName);
					} else {
						$dtoInterface = $interface;
					}
				}
			}
			if ($dtoInterface === null) {
				throw new InvalidArgumentException("No DTO interface found in " . $sourceReferenceClassName);
			}

			// Create the class
			$extends = null;
			$implements = [$w->useClass(ReferenceInterface::class), $w->useClass($dtoInterface->getName())];
			if ($sourceClassReflection->isInterface()) {
				$implements[] = $w->useClass($sourceReferenceClassName);
			} else {
				$extends = $w->useClass($sourceReferenceClassName);
			}
			$w->beginClass($shortTargetClassName, $extends, $implements);
			$w->writeln('use ' . $w->useClass(ReferenceTrait::class) . ';');

			// Create methods
			$this->generateIdMethods($w);
			$this->generateReferenceMethods($w, $definition);
			$this->generateTransitionMethods($w, $definition, $sourceClassReflection);
			$this->generateDataGetterMethods($w, $sourceClassReflection, $dtoInterface);

			$w->endClass();
		}
		// @codeCoverageIgnoreStart
		catch (\ReflectionException $ex) {
			throw new ReflectionException("Failed to generate Smalldb reference class: " . $definition->getMachineType(), 0, $ex);
		}
		// @codeCoverageIgnoreEnd

		$this->getClassGenerator()->addGeneratedClass($targetReferenceClassName, $w->getPhpCode());
		return $targetReferenceClassName;
	}


	private function generateIdMethods(PhpFileWriter $w)
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


	/**
	 * @throws \ReflectionException
	 * @throws LogicException
	 */
	private function generateDataGetterMethods(PhpFileWriter $w, ReflectionClass $sourceClassReflection, ReflectionClass $dtoInterface)
	{
		$dtoInterfaceAlias = $w->useClass($dtoInterface->getName());

		$w->writeln("/** @var " . $dtoInterfaceAlias . " */");
		$w->writeln('private $data = null;');

		$w->beginMethod('invalidateCache', [], 'void');
		{
			$w->writeln('$this->state = null;');
			$w->writeln('$this->data = null;');
			$w->writeln('$this->dataSource->invalidateCache($this->getMachineId());');
		}
		$w->endMethod();

		$referenceInterfaceReflection = new ReflectionClass(ReferenceInterface::class);

		foreach ($sourceClassReflection->getMethods() as $method) {
			$methodName = $method->getName();
			if (strncmp('get', $methodName, 3) === 0 && $method->isPublic() && !$w->hasMethod($methodName) && !$referenceInterfaceReflection->hasMethod($methodName)) {
				$argMethod = [];
				$argCall = [];
				foreach ($method->getParameters() as $param) {
					$argMethod[] = $w->getParamAsCode($param);
					$argCall[] = '$' . $param->name;
				}

				$returnType = $w->getTypeAsCode($method->getReturnType());

				$w->beginMethod($methodName, $argMethod, $returnType);
				$w->writeln("return (\$this->data ?? (\$this->data = \$this->dataSource->loadData(\$this->getMachineId(), \$this->state)))"
					. "->$methodName(" . join(", ", $argCall) . ");");
				$w->endMethod();
			}
		}

	}

}
