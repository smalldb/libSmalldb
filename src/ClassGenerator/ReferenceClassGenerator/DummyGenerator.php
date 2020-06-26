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
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\PhpFileWriter\PhpFileWriter;


class DummyGenerator extends AbstractGenerator
{

	/**
	 * Generate a new class implementing the missing methods in $sourceReferenceClassName.
	 */
	public function writeReferenceClass(PhpFileWriter $w, ReflectionClass $sourceClassReflection, StateMachineDefinition $definition): string
	{
		// Begin the Reference class
		$targetReferenceClassName = $this->beginReferenceClass($w, $sourceClassReflection);

		// Create methods
		$this->generateIdMethods($w);
		$this->generateDummyDataGetterMethods($w, $sourceClassReflection, $definition);
		$this->generateReferenceMethods($w, $definition);
		$this->generateTransitionMethods($w, $definition, $sourceClassReflection);

		$w->endClass();
		return $targetReferenceClassName;
	}


	private function generateDummyDataGetterMethods(PhpFileWriter $w, ReflectionClass $sourceClassReflection, StateMachineDefinition $definition)
	{
		$w->beginMethod('invalidateCache', [], 'void');
		{
			$w->writeln('$this->dataSource->invalidateCache($this->getMachineId());');
		}
		$w->endMethod();

		$this->generateFallbackExistsStateFunction($w, $sourceClassReflection, $definition,
			"\$this->dataSource->loadData(\$this->getMachineId()) !== null");
	}

}
