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

use JsonSerializable;
use Smalldb\Graph\Graph;
use Smalldb\StateMachine\Utils\SimpleJsonSerializableTrait;


class DiagramInfo implements JsonSerializable
{
	use SimpleJsonSerializableTrait;

	/** @var string */
	private $bpmnFileName;

	/** @var string */
	private $targetParticipant;

	/** @var string|null */
	private $svgFileName;

	/** @var Graph|null */
	private $bpmnGraph;


	public function __construct(string $bpmnFileName, string $targetParticipant, ?string $svgFileName, ?Graph $bpmnGraph)
	{
		$this->bpmnFileName = $bpmnFileName;
		$this->targetParticipant = $targetParticipant;
		$this->svgFileName = $svgFileName;
		$this->bpmnGraph = $bpmnGraph;
	}


	public function getBpmnFileName(): string
	{
		return $this->bpmnFileName;
	}


	public function getTargetParticipant(): string
	{
		return $this->targetParticipant;
	}


	public function getSvgFileName(): ?string
	{
		return $this->svgFileName;
	}


	public function getBpmnGraph(): ?Graph
	{
		return $this->bpmnGraph;
	}


}
