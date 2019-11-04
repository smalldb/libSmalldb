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

namespace Smalldb\StateMachine\GraphMLExtension;

use Smalldb\StateMachine\Definition\Builder\PreprocessorPass;


class GraphMLDefinitionPreprocessorPass implements PreprocessorPass
{
	/** @var string */
	private $graphmlFilename;

	/** @var string|null */
	private $group;


	/**
	 * GraphMLPreprocessor constructor.
	 */
	public function __construct(string $graphmlFilename, ?string $group = null)
	{
		$this->graphmlFilename = $graphmlFilename;
		$this->group = $group;
	}


	public function getGraphmlFilename(): string
	{
		return $this->graphmlFilename;
	}


	public function getGroup(): ?string
	{
		return $this->group;
	}

}
