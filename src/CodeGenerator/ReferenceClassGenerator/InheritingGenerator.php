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
use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\ReferenceTrait;
use Smalldb\StateMachine\Utils\PhpFileWriter;


class InheritingGenerator extends AbstractGenerator
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
			$this->generateHydratorMethod($w, $definition, $sourceClassReflection);

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


	/**
	 * @throws \ReflectionException
	 * @throws LogicException
	 */
	private function generateDataGetterMethods(PhpFileWriter $w, ReflectionClass $sourceClassReflection)
	{
		$w->writeln("/** @var bool */");
		$w->writeln('private $dataLoaded = false;');

		$w->beginMethod('invalidateCache', [], 'void');
		{
			$w->writeln('$this->state = null;');
			$w->writeln('$this->dataLoaded = false;');
			$w->writeln('$this->dataSource->invalidateCache($this->getId());');
		}
		$w->endMethod();

		$w->beginProtectedMethod('loadData', [], 'void');
		{
			$w->writeln("\$data = \$this->dataSource->loadData(\$this->getId(), \$this->state);");
			$w->beginBlock("if (\$data !== null)");
			{
				$w->writeln("static::hydrateFromArray(\$this, \$data);");
				$w->writeln("\$this->dataLoaded = true;");
			}
			$w->endBlock();
		}
		$w->endMethod();

		$referenceInterfaceReflection = new ReflectionClass(ReferenceInterface::class);

		foreach ($sourceClassReflection->getMethods() as $method) {
			$methodName = $method->getName();
			if ($methodName === 'getId' && !$w->hasMethod($methodName) && $method->isAbstract()) {
				if (!$sourceClassReflection->hasProperty('id')) {
					$w->writeln('protected $id;');
				}
				$w->beginMethod('getId', []);
				{
					$w->writeln("return \$this->id;");
				}
				$w->endMethod();
				$w->beginProtectedMethod('setId', ["\$id"], 'void');
				{
					$w->writeln("\$this->id = \$id;");
				}
				$w->endMethod();
			} else if (strncmp('get', $methodName, 3) === 0 && $method->isPublic() && !$w->hasMethod($methodName) && !$referenceInterfaceReflection->hasMethod($methodName)) {
				$argMethod = [];
				$argCall = [];
				foreach ($method->getParameters() as $param) {
					$argMethod[] = $w->getParamAsCode($param);
					$argCall[] = '$' . $param->name;
				}

				$returnType = $w->getTypeAsCode($method->getReturnType());

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

		if (!$sourceClassReflection->hasMethod('setId') && !$w->hasMethod('setId')) {
			$className = $sourceClassReflection->getName();
			throw new LogicException("Protected method $className::setId() is not defined. It is required by ReferenceTrait.");
		}
	}


	/**
	 * @throws \ReflectionException
	 */
	private function generateHydratorMethod(PhpFileWriter $w, StateMachineDefinition $definition, ReflectionClass $sourceClassReflection): void
	{
		if ($sourceClassReflection->hasMethod('hydrateFromArray')) {
			throw new LogicException('Method hydrateFromArray already defined in class ' . $sourceClassReflection->getName() . '.');
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
