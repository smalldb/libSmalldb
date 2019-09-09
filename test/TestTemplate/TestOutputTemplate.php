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


namespace Smalldb\StateMachine\Test\TestTemplate;

use Smalldb\StateMachine\Definition\Renderer\StateMachineExporter;
use Smalldb\StateMachine\Definition\StateMachineDefinition;


class TestOutputTemplate extends TestOutput implements Template
{
	public static $grafovatkoJsUrl = 'https://grafovatko.smalldb.org/dist/grafovatko.min.js';

	/** @var string */
	private $title;

	/** @var bool */
	private $hasGrafovatko = false;

	/** @var string[] */
	private $jsResources = [];

	/** @var string[] */
	private $htmlFragments = [];

	/** @var string|null */
	private $outputFilename = null;


	public function setTitle(string $title): self
	{
		$this->title = $title;
		return $this;
	}


	public function getTitle(): string
	{
		return $this->title;
	}


	public function addGrafovatko(): self
	{
		if ($this->hasGrafovatko) {
			return $this;
		}

		$grafovatkoJsFile = basename(static::$grafovatkoJsUrl);
		$grafovatkoJsPath = $this->outputPath($grafovatkoJsFile);

		// Download Grafovatko if not present
		if (!file_exists($grafovatkoJsPath)) {
			file_put_contents($grafovatkoJsPath, fopen(static::$grafovatkoJsUrl, 'rb'));
		}
		$this->addJs($grafovatkoJsFile);
		$this->hasGrafovatko = true;

		return $this;
	}


	public function addJs(string $jsUrl): self
	{
		$this->jsResources[$jsUrl] = $jsUrl;
		return $this;
	}


	public function addHtml(string $html): self
	{
		$this->htmlFragments[] = $html;
		return $this;
	}


	public function addStateMachineGraph(StateMachineDefinition $definition, bool $horizontalLayout = false): self
	{
		$this->addGrafovatko();

		$renderer = new StateMachineExporter($definition, $horizontalLayout);
		$this->addHtml("\n" . $renderer->exportSvgElement(['class' => 'graph']));
		return $this;
	}


	public function writeHtmlFile(string $targetFileName): void
	{
		$this->outputFilename = basename($targetFileName);

		if (!is_dir($this->outputDir) && !mkdir($this->outputDir)) {
			throw new \RuntimeException('Failed to create output directory: ' . $this->outputDir);
		}

		$html = $this->render();
		$targetPath = $this->outputPath($targetFileName);
		if (!file_put_contents($targetPath, $html)) {
			throw new \RuntimeException('Failed to write graph: ' . $targetPath);
		}
	}


	public function render(): string
	{
		if ($this->title === null) {
			throw new \LogicException('Missing title.');
		}

		return Html::document(["lang" => "en"],
			Html::head([],
				Html::title([], Html::text($this->title)),
				Html::meta(["charset" => "UTF-8"]),
				Html::link(['rel' => 'stylesheet', 'type' => 'text/css', 'href' => $this->resource('example.css')])),
			Html::body([],
				Html::article([],
					Html::h1([], Html::text($this->title)),
					$this->getHtml()),
				(new NavigationTemplate())->setActiveUrl($this->outputFilename)->render(),
				$this->generateScriptTags(),
				Html::script(['text/javascript'], Html::text($this->getGrafovatkoInitJsSnippet()))));
	}


	private function generateScriptTags(): string
	{
		$tags = [];
		foreach ($this->jsResources as $jsUrl) {
			$tags[] = Html::script(['type' => 'text/javascript', 'src' => $jsUrl]);
		}
		return join($tags, "\t\n");
	}


	private function getHtml(): string
	{
		return join($this->htmlFragments, "\n\n");
	}


	private function getGrafovatkoInitJsSnippet(): string
	{
		return <<<eof
			if (G) {
				console.log('Grafovatko %s.', G.version);
				const graphElements = document.getElementsByClassName('graph');
				window.grafovatkoView = [];
				for (const el of graphElements) {
					window.grafovatkoView.push(new G.GraphView(el));
				}
			}
			eof;
	}

}
