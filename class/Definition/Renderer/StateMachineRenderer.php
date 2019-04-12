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
use Smalldb\StateMachine\RuntimeException;


class StateMachineRenderer
{
	private $graphExporter;

	public function __construct()
	{
		$this->graphExporter = new GrafovatkoExporter();
		$this->graphExporter->addProcessor(new StateMachineProcessor());
	}


	public function grafovatkoExport(StateMachineDefinition $definition): array
	{
		$graph = $definition->getGraph();
		return $this->graphExporter->export($graph);
	}

	public function renderJsonString(StateMachineDefinition $definition, int $jsonOptions = 0): string
	{
		$graphJsonObject = $this->grafovatkoExport($definition);
		$graphJsonString = json_encode($graphJsonObject, JSON_NUMERIC_CHECK | $jsonOptions);
		return $graphJsonString;
	}


	public function renderJsonFile(StateMachineDefinition $definition, string $targetFileName): void
	{
		$graphJsonString = $this->renderJsonString($definition, JSON_PRETTY_PRINT);

		if ($graphJsonString !== false) {
			if (!file_put_contents($targetFileName, $graphJsonString)) {
				throw new RuntimeException('Failed to write state machine graph.');
			}
		} else {
			throw new RuntimeException('Failed to serialize state machine graph: ' . json_last_error_msg());
		}
	}

	public function renderSimpleHtmlPage(StateMachineDefinition $definition, string $targetFileName): void
	{
		$graphJsonString = $this->renderJsonString($definition, JSON_HEX_APOS | JSON_HEX_AMP);
		$title = htmlspecialchars($definition->getMachineType());
		$grafovatkoJsLink = 'https://grafovatko.smalldb.org/dist/grafovatko.min.js';

		$html = <<<EOF
			<!DOCTYPE HTML>
			<html>
			<head>
				<title>$title</title>
				<meta charset="UTF-8">
				<style type="text/css">
					body {
						text-align: center;
					}
					h1 {
						font-weight: normal;
					}
					svg#graph {
						margin: auto;
						overflow: visible;
					}
				</style>
			</head>
			<body>
				<h1>$title</h1>
	
				<svg width="1" height="1" id="graph" data-graph='$graphJsonString'></svg>

				<script type="text/javascript" src="$grafovatkoJsLink"></script>
				<script type="text/javascript">
					window.graphView = new G.GraphView('#graph');
				</script>
			</body>
			EOF;

		if (!file_put_contents($targetFileName, $html)) {
			throw new RuntimeException('Failed to write state machine graph.');
		}
	}

}
