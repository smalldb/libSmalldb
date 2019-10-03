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

use Smalldb\StateMachine\Definition\Builder\PreprocessorInterface;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilder;
use Smalldb\StateMachine\SourcesExtension\Definition\SourceFile;
use Smalldb\StateMachine\SourcesExtension\Definition\SourcesExtensionPlaceholder;


class DefinitionPreprocessor implements PreprocessorInterface
{
	/**
	 * @var string
	 */
	private $graphmlFilename;
	/**
	 * @var string
	 */
	private $group;

	/**
	 * GraphMLPreprocessor constructor.
	 */
	public function __construct(string $graphmlFilename, ?string $group = null)
	{
		$this->graphmlFilename = $graphmlFilename;
		$this->group = $group;
	}

	public function preprocessDefinition(StateMachineDefinitionBuilder $builder): void
	{
		/** @var SourcesExtensionPlaceholder $sourcesPlaceholder */
		$sourcesPlaceholder = $builder->getExtensionPlaceholder(SourcesExtensionPlaceholder::class);
		$sourcesPlaceholder->addSourceFile(new SourceFile($this->graphmlFilename));
		$builder->addMTime(filemtime($this->graphmlFilename));

		$reader = new GraphMLReader($builder);
		$reader->parseGraphMLFile($this->graphmlFilename, $this->group);

		/** @var GraphMLExtensionPlaceholder $placeholder */
		$placeholder = $builder->getExtensionPlaceholder(GraphMLExtensionPlaceholder::class);
		$placeholder->addDiagramInfo($this->graphmlFilename, $this->group, $reader->getGraph());

	}
}