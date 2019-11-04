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

namespace Smalldb\StateMachine\DoctrineExtension\Definition;

use Doctrine\ORM\EntityManager;
use Smalldb\StateMachine\Definition\Builder\Preprocessor;
use Smalldb\StateMachine\Definition\Builder\PreprocessorPass;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilder;


class DoctrineDefinitionPreprocessor implements Preprocessor
{

	/** @var EntityManager */
	private $entityManager;


	public function __construct(EntityManager $entityManager)
	{
		$this->entityManager = $entityManager;
	}


	public function supports(PreprocessorPass $preprocessorPass): bool
	{
		return $preprocessorPass instanceof DoctrineDefinitionPreprocessorPass;
	}


	public function preprocessDefinition(StateMachineDefinitionBuilder $builder, PreprocessorPass $preprocessorPass): void
	{
		/** @var DoctrineExtensionPlaceholder $ext */
		$ext = $builder->getExtensionPlaceholder(DoctrineExtensionPlaceholder::class);
		$ext->entityClassName = $preprocessorPass->getEntityClassName();

		$metadata = $this->entityManager->getClassMetadata($preprocessorPass->getEntityClassName());
		foreach ($metadata->getReflectionProperties() as $property) {
			/** @var \ReflectionProperty $property */
			$builder->addProperty($property->getName());
		}
	}

}
