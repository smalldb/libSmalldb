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

use Smalldb\StateMachine\BpmnExtension\Definition\BpmnDefinitionPreprocessor;
use Smalldb\StateMachine\GraphMLExtension\GraphMLDefinitionPreprocessor;


class StateMachineDefinitionBuilderFactory
{

	private PreprocessorList $preprocessorList;


	public function __construct()
	{
		$this->preprocessorList = new PreprocessorList();
	}


	/**
	 * Create the factory with default processors registered.
	 */
	public static function createDefaultFactory(): self
	{
		$factory = new self();
		$factory->addPreprocessor(new BpmnDefinitionPreprocessor());
		$factory->addPreprocessor(new GraphMLDefinitionPreprocessor());
		return $factory;
	}


	public function addPreprocessor(Preprocessor $preprocessor): void
	{
		$this->preprocessorList->addPreprocessor($preprocessor);
	}


	public function getPreprocessorList(): PreprocessorList
	{
		return $this->preprocessorList;
	}


	public function createDefinitionBuilder(): StateMachineDefinitionBuilder
	{
		return new StateMachineDefinitionBuilder($this->preprocessorList);
	}

}
