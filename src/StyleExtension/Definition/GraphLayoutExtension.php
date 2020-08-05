<?php declare(strict_types = 1);
/*
 * Copyright (c) 2020, Josef Kufner  <josef@kufner.cz>
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

namespace Smalldb\StateMachine\StyleExtension\Definition;

use Smalldb\StateMachine\Definition\ExtensionInterface;
use Smalldb\StateMachine\Definition\Renderer\StateMachineGraphProcessor;
use Smalldb\StateMachine\Definition\StateMachineGraph\StateMachineGraph;
use Smalldb\StateMachine\Utils\SimpleJsonSerializableTrait;


class GraphLayoutExtension implements ExtensionInterface, StateMachineGraphProcessor
{
	use SimpleJsonSerializableTrait;

	private ?string $layout;
	private ?array $layoutOptions;


	public function __construct(?string $layout, ?array $layoutOptions)
	{
		$this->layout = $layout;
		$this->layoutOptions = $layoutOptions;
	}


	public function getLayout(): ?string
	{
		return $this->layout;
	}


	public function getLayoutOptions(): ?array
	{
		return $this->layoutOptions;
	}


	public function processGraphAttrs(StateMachineGraph $graph, array &$exportedGraph)
	{
		if (($layout = $this->getLayout()) !== null) {
			$exportedGraph['layout'] = $layout;
		}
		if (($layoutOptions = $this->getLayoutOptions()) !== null) {
			$exportedGraph['layoutOptions'] = $layoutOptions;
		}
	}

}
