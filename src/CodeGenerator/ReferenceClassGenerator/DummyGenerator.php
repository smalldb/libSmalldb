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


class DummyGenerator extends AbstractGenerator
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
			$this->generateDummyId($w, $definition);

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


	private function generateDummyId(PhpFileWriter $w, StateMachineDefinition $definition)
	{
		$w->writeln('private $id = null;');

		$w->beginMethod('getId', [], '');
		{
			$w->writeln('return $this->id;');
		}
		$w->endMethod();

		$w->beginProtectedMethod('setId', ['$id'], 'void');
		{
			$w->writeln('$this->id = $id;');
		}
		$w->endMethod();

		$w->beginMethod('invalidateCache', [], 'void');
		{
			$w->writeln('$this->state = null;');
			$w->writeln('$this->dataSource->invalidateCache($this->getId());');
		}
		$w->endMethod();
	}

}
