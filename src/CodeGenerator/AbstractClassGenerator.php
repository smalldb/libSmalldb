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
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\Utils\PhpFileWriter;


abstract class AbstractClassGenerator
{
	// TODO: Remove this class, do not pass SmalldbClassGenerator to generators;
	//      return proper class representation instead.

	/** @var SmalldbClassGenerator */
	private $classGenerator;


	public function __construct(SmalldbClassGenerator $classGenerator)
	{
		$this->classGenerator = $classGenerator;
	}


	protected function getClassGenerator(): SmalldbClassGenerator
	{
		return $this->classGenerator;
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
