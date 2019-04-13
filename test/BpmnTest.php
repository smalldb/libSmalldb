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


namespace Smalldb\StateMachine\Test;

use PHPUnit\Framework\TestCase;
use Smalldb\StateMachine\BpmnGrafovatkoProcessor;
use Smalldb\StateMachine\BpmnReader;
use Smalldb\StateMachine\BpmnSvgPainter;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilder;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Definition\Renderer\StateMachineRenderer;
use Smalldb\StateMachine\Graph\Grafovatko\GrafovatkoExporter;
use Symfony\Component\DependencyInjection\Dumper\GraphvizDumper;


class BpmnTest extends TestCase
{
	private $outputDir;

	public function setUp(): void
	{
		$this->outputDir = __DIR__ . '/output';
		if (!is_dir($this->outputDir)) {
			$outputDirCreated = mkdir($this->outputDir);
			$this->assertTrue($outputDirCreated, 'Failed to create output directory: ' . $this->outputDir);
		}
	}

	public function tearDown(): void
	{
		$links = "";
		$files = glob($this->outputDir . "/*");
		natsort($files);
		foreach ($files as $filename) {
			$basename = basename($filename);
			if ($basename !== 'index.html') {
				$filenameHtml = htmlspecialchars(basename($filename));
				$links .= "\t\t\t<li><a href=\"$filenameHtml\" target=\"view\">$filenameHtml</a></li>\n";
			}
		}
		$firstFileHtml = htmlspecialchars('crud-item.html');

		$html = <<<EOF
				<!DOCTYPE HTML>
				<html>
				<head>
					<title>Test outputs</title>
					<meta charset="UTF-8">
					<style type="text/css">
						* {
							box-sizing: border-box;
						}
						html, body {
							display: flex;
							align-content: stretch;
							height: 100%;
							width: 100%;
							margin: 0;
							padding: 0;
						}
						nav, iframe {
							height: 100%;
							display: block;
						}
						nav {
							background: #eee;
							padding: 1em;
							border-right: 1px solid #666;
						}
						nav ul {
							list-style: none;
							margin: 0;
							padding: 0;
						}
						nav ul li {
							margin: 0.5em 0em;
						}
						iframe {
							border: none;
							flex-grow: 1;
						}
					</style>
				</head>
				<body>
					<nav>
						<ul>$links</ul>
					</nav>
					<iframe name="view" src="$firstFileHtml"></iframe>
				</body>
				EOF;

		file_put_contents($this->outputDir . '/index.html', $html);
	}


	public function testBasicExport()
	{
		// Build a simple CRUD machine
		$builder = new StateMachineDefinitionBuilder();
		$builder->setMachineType('crud-item');
		$builder->addState('Exists');
		$builder->addTransition('create', '', ['Exists']);
		$builder->addTransition('update', 'Exists', ['Exists']);
		$builder->addTransition('delete', 'Exists', ['']);
		$definition = $builder->build();
		$this->assertInstanceOf(StateMachineDefinition::class, $definition);

		// Render the result
		$htmlFilename = $this->outputDir . '/' . $definition->getMachineType() . '.html';
		$renderer = new StateMachineRenderer();
		$renderer->renderSimpleHtmlPage($definition, $htmlFilename);
		$this->assertFileExists($htmlFilename);
	}


	/**
	 * @dataProvider bpmnFileProvider
	 */
	public function testBpmnDiagram(string $bpmnFilename, ?string $svgFilename)
	{
		$this->assertFileExists($bpmnFilename);

		$machineType = preg_replace('/\.[^.]*$/', '', basename($bpmnFilename));

		$bpmnReader = BpmnReader::readBpmnFile($bpmnFilename);
		$bpmnReader->inferStateMachine("Participant_StateMachine");
		$bpmnGraph = $bpmnReader->getBpmnGraph();

		$htmlFilename = $this->outputDir . '/' . basename($bpmnFilename) . '.html';
		$renderer = new GrafovatkoExporter();
		$renderer->addProcessor(new BpmnGrafovatkoProcessor());
		$renderer->exportHtmlFile($bpmnGraph, $htmlFilename);

		if ($svgFilename) {
			$this->assertFileExists($svgFilename);
			$svgContent = file_get_contents($svgFilename);
			$svgPainter = new BpmnSvgPainter();
			$colorizedSvgContent = $svgPainter->colorizeSvgFile($svgContent, $bpmnGraph, [], '');
			$targetSvgFilename  = $this->outputDir . '/' . basename($svgFilename);
			file_put_contents($targetSvgFilename, $colorizedSvgContent);
			$this->assertFileExists($targetSvgFilename);
		}

		$this->markTestIncomplete();

		// Render the result
		$htmlFilename = $this->outputDir . '/' . $definition->getMachineType() . '.html';
		$renderer = new StateMachineRenderer();
		$renderer->renderSimpleHtmlPage($definition, $htmlFilename);
		$this->assertFileExists($htmlFilename);
	}


	public function bpmnFileProvider()
	{
		$filesPattern = __DIR__ . '/Example/Bpmn/*.bpmn';
		foreach (glob($filesPattern) as $bpmnFilename) {
			$basename = str_replace('.bpmn', '', basename($bpmnFilename));
			$svgFilename = dirname($bpmnFilename) . '/' . $basename . '.svg';
			yield $basename => [$bpmnFilename, file_exists($svgFilename) ? $svgFilename : null];
		}
	}

}
