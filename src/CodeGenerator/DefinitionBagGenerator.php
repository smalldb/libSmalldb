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


use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\InvalidArgumentException;
use Smalldb\StateMachine\SmalldbDefinitionBagInterface;
use Smalldb\StateMachine\Utils\PhpFileWriter;

class DefinitionBagGenerator extends AbstractClassGenerator
{

	public function generateDefinitionBagClass(string $className, SmalldbDefinitionBagInterface $definitionBag): string
	{
		$shortTargetClassName = PhpFileWriter::getShortClassName($className);

		$w = new PhpFileWriter();
		$w->setFileHeader(__CLASS__);
		$w->setNamespace($w->getClassNamespace($className));
		$w->setClassName($shortTargetClassName);

		// Prepare definition method names
		$definitionMethodNames = [];
		foreach ($definitionBag->getAllMachineTypes() as $machineType) {
			$definitionMethodNames[$machineType] = $w->getIdentifier('createDefinition', $machineType);
		}

		// Generate the SmalldbDefinitionBagInterface implementation
		$w->beginClass($shortTargetClassName, null, [$w->useClass(SmalldbDefinitionBagInterface::class)]);
		$this->generateGetAllMachineTypesMethod($w, $definitionBag->getAllMachineTypes());
		$this->generateGetAllDefinitions($w);
		$aliases = $definitionBag->getAllAliases();
		$this->generateGetAllAliasesMethod($w, $aliases);
		$this->generateGetDefinitionMethod($w, $definitionMethodNames, $aliases);

		// Definition methods
		foreach ($definitionMethodNames as $machineType => $methodName) {
			$this->generateDefinitionMethod($w, $methodName, $definitionBag->getDefinition($machineType));
		}
		$w->endClass();

		$this->getClassGenerator()->addGeneratedClass($className, $w->getPhpCode());
		return $className;
	}


	private function generateDefinitionMethod(PhpFileWriter $w, string $methodName, StateMachineDefinition $definition)
	{
		$w->beginPrivateMethod($methodName);
		// TODO: Generate proper PHP code using anonymous classes rather than unserialize a blob.
		$w->writeln("return unserialize(%s);", serialize($definition));
		$w->endMethod();
	}


	private function generateGetAllMachineTypesMethod(PhpFileWriter $w, array $machineTypes)
	{
		$w->beginMethod('getAllMachineTypes', [], 'array');
		$w->writeln('return %s;', $machineTypes);
		$w->endMethod();
	}

	private function generateGetAllDefinitions(PhpFileWriter $w)
	{
		$w->beginMethod('getAllDefinitions', [], 'iterable');
		$w->beginBlock('foreach ($this->getAllMachineTypes() as $machineType)');
		$w->writeln('yield $machineType => $this->getDefinition($machineType);');
		$w->endBlock();
		$w->endMethod();
	}

	private function generateGetAllAliasesMethod(PhpFileWriter $w, array $aliases)
	{
		$w->beginMethod('getAllAliases', [], 'array');
		$w->writeln('return %s;', $aliases);
		$w->endMethod();
	}

	private function generateGetDefinitionMethod(PhpFileWriter $w, array $definitionMethodNames, array $aliases)
	{
		$definitionCacheVar = [];

		foreach ($definitionMethodNames as $machineType => $methodName) {
			$definitionCacheVar[$machineType] = $cacheVar = $w->getIdentifier('definition', $machineType);
			$w->writeln("private \$$cacheVar = null;");
		}

		$w->beginMethod('getDefinition', ['string $machineType'], $w->useClass(StateMachineDefinition::class));
		$w->beginBlock('switch ($machineType)');
		{
			// Defined machine types
			$w->comment('Definitions');
			foreach ($definitionMethodNames as $machineType => $methodName) {
				$cacheVar = $definitionCacheVar[$machineType];
				$w->writeln("case %s: return \$this->$cacheVar ?? (\$this->$cacheVar = \$this->$methodName());", $machineType);
			}

			// Aliases
			if (!empty($aliases)) {
				$w->comment('Aliases');
				foreach ($aliases as $alias => $machineType) {
					$methodName = $definitionMethodNames[$machineType];
					$cacheVar = $definitionCacheVar[$machineType];
					$w->writeln("case %s: return \$this->$cacheVar ?? (\$this->$cacheVar = \$this->$methodName());", $alias);
				}
			}

			$w->writeln('');
			$w->writeln("default: throw new " . $w->useClass(InvalidArgumentException::class) . "(\"Undefined machine type: \$machineType\");");
		}
		$w->endBlock();
		$w->endMethod();
	}

}
