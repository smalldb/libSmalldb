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

namespace Smalldb\StateMachine\BpmnExtension\Definition;

use Smalldb\StateMachine\BpmnExtension\BpmnReader;
use Smalldb\StateMachine\Definition\Builder\Preprocessor;
use Smalldb\StateMachine\Definition\Builder\PreprocessorPass;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilder;
use Smalldb\StateMachine\SourcesExtension\Definition\SourceFile;
use Smalldb\StateMachine\SourcesExtension\Definition\SourcesExtensionPlaceholder;


class BpmnDefinitionPreprocessor implements Preprocessor
{

	public function supports(PreprocessorPass $preprocessorPass): bool
	{
		return $preprocessorPass instanceof BpmnDefinitionPreprocessorPass;
	}


	public function preprocessDefinition(StateMachineDefinitionBuilder $builder, PreprocessorPass $preprocessorPass): void
	{
		/** @var SourcesExtensionPlaceholder $sourcesPlaceholder */
		$sourcesPlaceholder = $builder->getExtensionPlaceholder(SourcesExtensionPlaceholder::class);
		$sourcesPlaceholder->addSourceFile(new SourceFile($preprocessorPass->getBpmnFilename()));
		$builder->addMTime(filemtime($preprocessorPass->getBpmnFilename()));

		$reader = BpmnReader::readBpmnFile($preprocessorPass->getBpmnFilename());
		$reader->inferStateMachine($builder, $preprocessorPass->getTargetParticipant());

		/** @var BpmnExtensionPlaceholder $placeholder */
		$placeholder = $builder->getExtensionPlaceholder(BpmnExtensionPlaceholder::class);
		$placeholder->addDiagramInfo($preprocessorPass->getBpmnFilename(), $preprocessorPass->getTargetParticipant(),
			$preprocessorPass->getSvgFile(), $reader->getBpmnGraph());
	}

}
