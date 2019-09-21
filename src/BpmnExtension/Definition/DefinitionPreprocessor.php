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
use Smalldb\StateMachine\Definition\Builder\PreprocessorInterface;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilder;


class DefinitionPreprocessor implements PreprocessorInterface
{
	/** @var string */
	private $bpmnFilename;

	/** @var string */
	private $targetParticipant;

	/** @var string|null */
	private $svgFile;


	public function __construct(string $bpmnFilename, string $targetParticipant, ?string $svgFile = null)
	{
		$this->bpmnFilename = $bpmnFilename;
		$this->targetParticipant = $targetParticipant;
		$this->svgFile = $svgFile;
	}

	public function preprocessDefinition(StateMachineDefinitionBuilder $builder): void
	{
		$reader = BpmnReader::readBpmnFile($this->bpmnFilename);
		$reader->inferStateMachine($builder, $this->targetParticipant);

		/** @var BpmnExtensionPlaceholder $placeholder */
		$placeholder = $builder->getExtensionPlaceholder(BpmnExtensionPlaceholder::class);
		$placeholder->addDiagramInfo($this->bpmnFilename, $this->targetParticipant,
			$this->svgFile, $reader->getBpmnGraph());

	}
}
