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
use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\Utils\PhpFileWriter;


class InheritingGenerator extends AbstractGenerator
{

	/**
	 * Generate a new class implementing the missing methods in $sourceReferenceClassName.
	 */
	public function writeReferenceClass(PhpFileWriter $w, ReflectionClass $sourceClassReflection, StateMachineDefinition $definition): string
	{
		// Begin the Reference class
		$targetReferenceClassName = $this->beginReferenceClass($w, $sourceClassReflection);

		// Create methods
		$this->generateReferenceMethods($w, $definition);
		$this->generateIdMethods($w);
		$this->generateTransitionMethods($w, $definition, $sourceClassReflection);
		$this->generateDataGetterMethods($w, $definition, $sourceClassReflection);
		$this->generateHydratorMethod($w, $definition, $sourceClassReflection);

		$w->endClass();
		return $targetReferenceClassName;
	}


	private function generateDataGetterMethods(PhpFileWriter $w, StateMachineDefinition $definition, ReflectionClass $sourceClassReflection)
	{
		$w->writeln("/** @var bool */");
		$w->writeln('private $dataLoaded = false;');

		$w->beginMethod('invalidateCache', [], 'void');
		{
			$w->writeln('$this->dataLoaded = false;');
			$w->writeln('$this->dataSource->invalidateCache($this->getMachineId());');
		}
		$w->endMethod();

		$w->beginProtectedMethod('loadData', [], 'bool');
		{
			$w->writeln("\$data = \$this->dataSource->loadData(\$this->getMachineId());");
			$w->beginBlock("if (\$data !== null)");
			{
				$w->writeln("static::hydrateFromArray(\$this, \$data);");
				$w->writeln("\$this->dataLoaded = true;");
				$w->writeln("return true;");
			}
			$w->midBlock("else");
			{
				$w->writeln("return false;");
			}
			$w->endBlock();
		}
		$w->endMethod();

		$referenceInterfaceReflection = new ReflectionClass(ReferenceInterface::class);

		foreach ($sourceClassReflection->getMethods() as $method) {
			$methodName = $method->getName();
			if (strncmp('get', $methodName, 3) === 0 && $method->isPublic() && !$w->hasMethod($methodName) && !$referenceInterfaceReflection->hasMethod($methodName)) {
				$w->beginMethodOverride($method, $argCall);
				$w->beginBlock("if (\$this->dataLoaded || \$this->loadData())");
				{
					$w->writeln("return parent::$methodName(" . join(', ', $argCall) . ");");
				}
				$w->midBlock("else");
				{
					$w->writeln("throw new " . $w->useClass(NotExistsException::class) . "(\"Cannot load data in the Not Exists state.\");");
				}
				$w->endBlock();
				$w->endMethod();
			}
		}

		$this->generateFallbackExistsStateFunction($w, $sourceClassReflection, $definition,
			"\$this->dataLoaded || \$this->loadData()");
	}


	private function generateHydratorMethod(PhpFileWriter $w, StateMachineDefinition $definition, ReflectionClass $sourceClassReflection): void
	{
		if ($sourceClassReflection->hasMethod('hydrateFromArray')) {
			throw new InvalidArgumentException('Method hydrateFromArray already defined in class ' . $sourceClassReflection->getName() . '.');
		}

		$w->beginStaticMethod('hydrateFromArray', ['self $target', 'array $row'], 'void');
		{
			foreach ($definition->getProperties() as $property) {
				$name = $property->getName();
				$typehint = $property->getType();

				// Fallback: Get a typehint from getter return type
				if ($typehint === null) {
					$getterName = 'get' . ucfirst($name);
					$returnType = $sourceClassReflection->hasMethod($getterName)
						? $sourceClassReflection->getMethod($getterName)->getReturnType()
						: null;
					$typehint = $returnType ? $returnType->getName() : null;
				}

				// TODO: Support mapping of multiple SQL columns into a single machine property.
				// TODO: Support mapping of a single SQL column (e.g., JSON object) into multiple machine properties.

				// Convert value to fit the typehint
				switch ($typehint) {
					case 'int':
					case 'float':
					case 'bool':
					case 'string':
						$w->writeln("\$target->$name = isset(\$row[%s]) ? ($typehint) \$row[%s] : null;", $name, $name);
						break;

					default:
						if ($typehint && class_exists($typehint)) {
							$c = $w->useClass($typehint);
							$w->writeln("\$target->$name = (\$v = \$row[%s] ?? null) instanceof $c || \$v === null ? \$v : new $c(\$v);", $name);
						} else {
							$w->writeln("\$target->$name = \$row[%s] ?? null;", $name);
						}
						break;
				}
			}
			$w->writeln();

			$w->writeln("\$target->state = isset(\$row['state']) ? (string) \$row['state'] : null;");
			$w->writeln("\$target->dataLoaded = true;");
		}
		$w->endMethod();
	}

}
