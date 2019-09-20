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

namespace Smalldb\StateMachine\BpmnExtension\Annotation;

use Smalldb\StateMachine\Annotation\AbstractIncludeAnnotation;
use Smalldb\StateMachine\BpmnExtension\BpmnExtensionPlaceholder;
use Smalldb\StateMachine\BpmnExtension\DefinitionPreprocessor;
use Smalldb\StateMachine\Definition\Builder\StateMachineBuilderApplyInterface;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilder;


/**
 * Include GraphML state chart file
 *
 * @Annotation
 * @Target({"CLASS"})
 */
class IncludeBPMN extends AbstractIncludeAnnotation implements StateMachineBuilderApplyInterface
{
	/**
	 * @var string
	 * @Required
	 */
	public $fileName;

	/**
	 * @var string
	 * @Required
	 */
	public $targetParticipant;

	/** @var string|null */
	public $svgFile = null;


	public function applyToBuilder(StateMachineDefinitionBuilder $builder): void
	{
		/** @var BpmnExtensionPlaceholder $placeholder */
		$placeholder = $builder->getExtensionPlaceholder(BpmnExtensionPlaceholder::class);
		$placeholder->fileName = $this->canonizeFileName($this->fileName);
		$placeholder->targetParticipant = $this->targetParticipant;
		$placeholder->svgFile = $this->canonizeFileName($this->svgFile);

		$builder->addPreprocessor(new DefinitionPreprocessor($placeholder->fileName,
			$placeholder->targetParticipant, $placeholder->svgFile));
	}

}
