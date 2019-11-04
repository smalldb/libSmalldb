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

use Smalldb\StateMachine\Definition\Builder\Preprocessor;
use Smalldb\StateMachine\Definition\Builder\PreprocessorPass;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilder;
use Smalldb\StateMachine\SourcesExtension\Definition\SourceFile;
use Smalldb\StateMachine\SourcesExtension\Definition\SourcesExtensionPlaceholder;


class GraphMLDefinitionPreprocessor implements Preprocessor
{

	public function supports(PreprocessorPass $preprocessorPass): bool
	{
		return $preprocessorPass instanceof GraphMLDefinitionPreprocessorPass;
	}


	public function preprocessDefinition(StateMachineDefinitionBuilder $builder, PreprocessorPass $preprocessorPass): void
	{
		/** @var SourcesExtensionPlaceholder $sourcesPlaceholder */
		$sourcesPlaceholder = $builder->getExtensionPlaceholder(SourcesExtensionPlaceholder::class);
		$sourcesPlaceholder->addSourceFile(new SourceFile($preprocessorPass->getGraphmlFilename()));
		$builder->addMTime(filemtime($preprocessorPass->getGraphmlFilename()));

		$reader = new GraphMLReader($builder);
		$reader->parseGraphMLFile($preprocessorPass->getGraphmlFilename(), $preprocessorPass->getGroup());

		/** @var GraphMLExtensionPlaceholder $placeholder */
		$placeholder = $builder->getExtensionPlaceholder(GraphMLExtensionPlaceholder::class);
		$placeholder->addDiagramInfo($preprocessorPass->getGraphmlFilename(), $preprocessorPass->getGroup(), $reader->getGraph());
	}

}
