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
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\InvalidArgumentException;
use Smalldb\StateMachine\ReferenceDataSource\NotExistsException;
use Smalldb\StateMachine\ReferenceDataSource\StatefulEntity;
use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\Utils\PhpFileWriter;


class DecoratingGenerator extends AbstractGenerator
{

	/**
	 * Generate a new class implementing the missing methods in $sourceReferenceClassName.
	 */
	public function writeReferenceClass(PhpFileWriter $w, ReflectionClass $sourceClassReflection, StateMachineDefinition $definition): string
	{
		// Detect DTO interface
		$dtoInterface = null;
		foreach ($sourceClassReflection->getInterfaces() as $interface) {
			if (!$interface->implementsInterface(ReferenceInterface::class)) {
				if ($dtoInterface) {
					// TODO: This may not be that bad. We may support multiple DTOs.
					throw new InvalidArgumentException("Multiple DTO interfaces found in " . $sourceClassReflection->getName());
				} else {
					$dtoInterface = $interface;
				}
			}
		}
		if ($dtoInterface === null) {
			throw new InvalidArgumentException("No DTO interface found in " . $sourceClassReflection->getName());
		}

		// Begin the Reference class
		$targetReferenceClassName = $this->beginReferenceClass($w, $sourceClassReflection, [$w->useClass($dtoInterface->getName())]);

		// Create methods
		$this->generateIdMethods($w);
		$this->generateReferenceMethods($w, $definition);
		$this->generateTransitionMethods($w, $definition, $sourceClassReflection);
		$this->generateDataGetterMethods($w, $definition, $sourceClassReflection, $dtoInterface);
		$this->generateHydratorMethod($w, $sourceClassReflection, $dtoInterface);

		$w->endClass();
		return $targetReferenceClassName;
	}


	private function generateDataGetterMethods(PhpFileWriter $w, StateMachineDefinition $definition, ReflectionClass $sourceClassReflection, ReflectionClass $dtoInterface)
	{
		$dtoInterfaceAlias = $w->useClass($dtoInterface->getName());

		$w->writeln("/** @var " . $dtoInterfaceAlias . " */");
		$w->writeln('private $data = null;');

		$w->beginMethod('invalidateCache', [], 'void');
		{
			$w->writeln('$this->data = null;');
			$w->writeln('$this->dataSource->invalidateCache($this->getMachineId());');
		}
		$w->endMethod();

		// Implement missing methods from $dtoInterface
		foreach ($dtoInterface->getMethods() as $dtoMethod) {
			$dtoMethodName = $dtoMethod->getName();
			$sourceMethod = $sourceClassReflection->getMethod($dtoMethodName);

			if (!$w->hasMethod($dtoMethodName) && $dtoMethod->isPublic() && (!$sourceMethod || $sourceMethod->isAbstract())) {
				$w->beginMethodOverride($dtoMethod, $parentCallArgs);
				{
					$w->beginBlock("if (\$this->data === null && (\$this->data = \$this->dataSource->loadData(\$this->getMachineId())) === null)");
					{
						$w->writeln("throw new " . $w->useClass(NotExistsException::class) . "(\"Cannot load data in the Not Exists state.\");");
					}
					$w->midBlock("else");
					{
						$w->writeln("return \$this->data->$dtoMethodName(" . join(", ", $parentCallArgs) . ");");
					}
					$w->endBlock();
				}
				$w->endMethod();
			}
		}

		// Implement state method
		if (!$w->hasMethod('getState') && ($stateMethod = $sourceClassReflection->getMethod('getState')) && $stateMethod->isAbstract()) {
			$w->beginMethod('getState', [], 'string');
			{
				$w->beginBlock("if (\$this->data === null)");
				{
					$w->writeln("\$this->data = \$this->dataSource->loadData(\$this->getMachineId());");
				}
				$w->endBlock();

				$notExists = $w->useClass(ReferenceInterface::class) . '::NOT_EXISTS';
				$states = $definition->getStates();
				switch (count($states)) {
					case 0:
					case 1:
						$w->writeln("return $notExists;");
						break;

					case 2:
						// There are two states: NOT_EXISTS and EXISTS. If there are any data, it EXISTS.
						$theOtherState = null;
						foreach ($states as $state) {
							if ($state->getName() !== ReferenceInterface::NOT_EXISTS) {
								$theOtherState = $state->getName();
								break;
							}
						}
						$w->writeln("return \$this->data === null ? $notExists : %s;", $theOtherState);
						break;

					default:
						$w->beginBlock("switch (true)");
						{
							$w->writeln("case \$this->data instanceof " . $w->useClass(StatefulEntity::class) . ":"
								. " return \$this->data->getState() ?: $notExists;");
							$w->writeln("case is_array(\$this->data): return \$this->data['state'] ?: $notExists;");
							$w->writeln("case isset(\$this->data->state): return \$this->data->state ?: $notExists;");
							$w->writeln("default: return $notExists;");
						}
						$w->endBlock();
						break;
				}
			}
			$w->endMethod();
		}

		// Implement the remaining abstract methods
		foreach ($sourceClassReflection->getMethods() as $method) {
			$methodName = $method->getName();

			if ($method->isAbstract() && !$w->hasMethod($methodName)) {
				$returnType = $method->getReturnType();

				// Implement data loader methods
				if ($returnType && $returnType->getName() === $dtoInterface->getName() && empty($method->getParameters())) {
					$w->beginMethodOverride($method, $parentCallArgs);
					{
						$w->beginBlock("if (\$this->data === null && (\$this->data = \$this->dataSource->loadData(\$this->getMachineId())) === null)");
						{
							$w->writeln("return null;");
						}
						$w->midBlock("else");
						{
							// FIXME: To clone or not to clone?
							$w->writeln("return clone \$this->data;");
						}
						$w->endBlock();
					}
					$w->endBlock();
				}
			}
		}

	}


	private function generateHydratorMethod(PhpFileWriter $w, ReflectionClass $sourceClassReflection, ReflectionClass $dtoInterface): void
	{
		if ($sourceClassReflection->hasMethod('hydrateWithDTO')) {
			throw new InvalidArgumentException('Method hydrateWithDTO already defined in class ' . $sourceClassReflection->getName() . '.');
		}

		$w->beginStaticMethod('hydrateWithDTO', ['self $target', '?' . $w->useClass($dtoInterface->getName()) . ' $obj'], 'void');
		{
			$w->writeln("\$target->data = \$obj;");
		}
		$w->endMethod();
	}

}
