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
use Smalldb\StateMachine\Definition\Renderer\StateMachineEdgeProcessor;
use Smalldb\StateMachine\Definition\Renderer\StateMachineNodeProcessor;
use Smalldb\StateMachine\Definition\StateMachineGraph\StateMachineEdge;
use Smalldb\StateMachine\Definition\StateMachineGraph\StateMachineNode;
use Smalldb\StateMachine\Utils\SimpleJsonSerializableTrait;


class StyleExtension implements ExtensionInterface, StateMachineNodeProcessor, StateMachineEdgeProcessor
{
	use SimpleJsonSerializableTrait;

	private ?string $color;


	public function __construct(?string $color)
	{
		$this->color = $color;
	}


	public function getColor(): ?string
	{
		return $this->color;
	}


	public function processNodeAttrs(StateMachineNode $node, array &$exportedNode)
	{
		$exportedNode['fill'] = $this->getColor() ?? "#eee";
	}


	public function processEdgeAttrs(StateMachineEdge $edge, array &$exportedEdge)
	{
		$exportedEdge['color'] = $this->getColor() ?? "#000";
	}

}
