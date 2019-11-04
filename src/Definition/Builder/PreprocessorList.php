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

namespace Smalldb\StateMachine\Definition\Builder;


class PreprocessorList implements Preprocessor
{
	/** @var Preprocessor[] */
	private $preprocessors = [];


	public function addPreprocessor(Preprocessor $preprocessor): void
	{
		$this->preprocessors[] = $preprocessor;
	}


	public function supports(PreprocessorPass $preprocessorPass): bool
	{
		foreach ($this->preprocessors as $preprocessor) {
			if ($preprocessor->supports($preprocessorPass)) {
				return true;
			}
		}
		return false;
	}


	public function preprocessDefinition(StateMachineDefinitionBuilder $builder, PreprocessorPass $preprocessorPass): void
	{
		foreach ($this->preprocessors as $preprocessor) {
			if ($preprocessor->supports($preprocessorPass)) {
				$preprocessor->preprocessDefinition($builder, $preprocessorPass);
				return;
			}
		}

		throw new PreprocessorPassException(sprintf("Preprocessor pass %s not supported in machine %s.",
			get_class($preprocessorPass), $builder->getMachineType()));
	}

}
