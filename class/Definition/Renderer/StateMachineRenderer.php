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

namespace Smalldb\StateMachine\Definition\Renderer;

use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Graph\Grafovatko\GrafovatkoExporter;


class StateMachineRenderer
{
	private $graphExporter;

	public function __construct(bool $horizontalLayout = false)
	{
		$this->graphExporter = new GrafovatkoExporter();
		$this->graphExporter->addProcessor(new StateMachineProcessor($horizontalLayout));
	}


	public function grafovatkoExport(StateMachineDefinition $definition): array
	{
		$graph = $definition->getGraph();
		return $this->graphExporter->export($graph);
	}

	public function renderJsonString(StateMachineDefinition $definition, int $jsonOptions = 0): string
	{
		return $this->graphExporter->exportJsonString($definition->getGraph(), $jsonOptions);
	}


	public function renderJsonFile(StateMachineDefinition $definition, string $targetFileName, int $jsonOptions = JSON_PRETTY_PRINT): void
	{
		$this->graphExporter->exportJsonFile($definition->getGraph(), $targetFileName, $jsonOptions);
	}


	public function renderSvgElement(StateMachineDefinition $definition, array $attrs = []): string
	{
		return $this->graphExporter->exportSvgElement($definition->getGraph(), $attrs);
	}

	public function renderSimpleHtmlPage(StateMachineDefinition $definition, string $targetFileName): void
	{
		$this->graphExporter->exportHtmlFile($definition->getGraph(), $targetFileName);
	}

}
