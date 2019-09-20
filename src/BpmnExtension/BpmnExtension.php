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

namespace Smalldb\StateMachine\BpmnExtension;

use Smalldb\StateMachine\Definition\ExtensionInterface;
use Smalldb\StateMachine\Utils\SimpleJsonSerializableTrait;


class BpmnExtension implements ExtensionInterface
{
	use SimpleJsonSerializableTrait;

	/** @var string */
	private $fileName;

	/** @var string */
	private $targetParticipant;

	/** @var string|null */
	private $svgFile;


	public function __construct(string $fileName, string $targetParticipant, ?string $svgFile = null)
	{
		$this->fileName = $fileName;
		$this->targetParticipant = $targetParticipant;
		$this->svgFile = $svgFile;
	}


	public function getFileName(): string
	{
		return $this->fileName;
	}


	public function getTargetParticipant(): string
	{
		return $this->targetParticipant;
	}


	public function getSvgFile(): ?string
	{
		return $this->svgFile;
	}

}
