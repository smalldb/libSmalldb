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

use Smalldb\StateMachine\Definition\Builder\ExtensionPlaceholderInterface;
use Smalldb\StateMachine\Definition\ExtensionInterface;
use Smalldb\Graph\Graph;


class GraphMLExtensionPlaceholder implements ExtensionPlaceholderInterface
{

	/** @var DiagramInfo[] */
	public $diagramInfo = [];


	public function __construct()
	{
	}


	public function buildExtension(): ?ExtensionInterface
	{
		return new GraphMLExtension($this->diagramInfo);
	}

	public function addDiagramInfo(string $graphmlFilename, ?string $group, ?Graph $graph)
	{
		$this->diagramInfo[] = new DiagramInfo($graphmlFilename, $group, $graph);
	}

}
