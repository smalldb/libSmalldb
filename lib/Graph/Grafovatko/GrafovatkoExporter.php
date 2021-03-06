<?php declare(strict_types = 1);
/*
 * Copyright (c) 2018, Josef Kufner  <josef@kufner.cz>
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


namespace Smalldb\Graph\Grafovatko;

use RuntimeException;
use Smalldb\Graph\Edge;
use Smalldb\Graph\Graph;
use Smalldb\Graph\NestedGraph;
use Smalldb\Graph\Node;


class GrafovatkoExporter
{
	private Graph $graph;

	public static string $grafovatkoJsLink = 'https://grafovatko.smalldb.org/dist/grafovatko.min.js';

	/** @var ProcessorInterface[] */
	private array $processors = [];

	/**
	 * Global graph IDs prefix
	 *
	 * @var string
	 */
	private string $prefix = '';


	public function __construct(Graph $graph)
	{
		$this->graph = $graph;
	}


	public function addProcessor(ProcessorInterface $processor): self
	{
		$this->processors[] = $processor;
		$processor->setPrefix($this->prefix);
		return $this;
	}


	public function export(): array
	{
		$jsonObject = $this->exportNestedGraph($this->graph);

		foreach ($this->processors as $processor) {
			$extraSvg = $processor->getExtraSvgElements($this->graph);
			if (!empty($extraSvg)) {
				foreach ($extraSvg as $el) {
					$jsonObject['extraSvg'][] = $el;
				}
			}
		}

		return $jsonObject;
	}


	public function exportJsonString(int $jsonOptions = 0): string
	{
		$jsonObject = $this->export();
		$jsonString = json_encode($jsonObject, JSON_NUMERIC_CHECK | $jsonOptions);
		if ($jsonString === false) {
			throw new RuntimeException('Failed to serialize graph: ' . json_last_error_msg());  // @codeCoverageIgnore
		}
		return $jsonString;
	}


	public function exportSvgElement(array $attrs = []): string
	{
		$jsonString = $this->exportJsonString(JSON_HEX_APOS | JSON_HEX_AMP);

		$attrsHtml = "";
		foreach ($attrs as $attr => $value) {
			if ($value !== null) {
				$attrsHtml .= " $attr=\"" . htmlspecialchars($value) . "\"";
			}
		}

		return "<svg$attrsHtml width=\"1\" height=\"1\" data-graph='$jsonString'></svg>";
	}


	public function exportHtmlFile(string $targetFileName, ?string $title = null): void
	{
		$titleHtml = htmlspecialchars($title ?? basename($targetFileName));
		$grafovatkoJsFile = basename(static::$grafovatkoJsLink);
		$grafovatkoJsLink = file_exists(dirname($targetFileName) . '/' . $grafovatkoJsFile)
			? $grafovatkoJsFile : static::$grafovatkoJsLink;

		$svgElement = $this->exportSvgElement(['id' => 'graph']);

		$html = <<<EOF
			<!DOCTYPE HTML>
			<html>
			<head>
				<title>$titleHtml</title>
				<meta charset="UTF-8" />
				<style type="text/css">
					html, body {
						display: flex;
						align-items: center;
						min-height: 100%;
						margin: auto;
						padding: 0;
					}
					svg#graph {
						margin: auto;
						padding: 1em;
						overflow: visible;
					}
				</style>
			</head>
			<body>
				$svgElement
				<script type="text/javascript" src="$grafovatkoJsLink"></script>
				<script type="text/javascript">
					window.graphView = new G.GraphView('#graph');
				</script>
			</body>
			</html>
			EOF;

		if (!file_put_contents($targetFileName, $html)) {
			throw new RuntimeException('Failed to write graph.');  //@codeCoverageIgnore
		}
	}


	public function getPrefix(): string
	{
		return $this->prefix;
	}


	public function setPrefix(string $prefix): self
	{
		$this->prefix = $prefix;
		foreach ($this->processors as $processor) {
			$processor->setPrefix($prefix);
		}
		return $this;
	}


	protected function processGraph(NestedGraph $graph, array $exportedGraph): array
	{
		foreach ($this->processors as $processor) {
			$exportedGraph = $processor->processGraph($graph, $exportedGraph);
		}
		return $exportedGraph;
	}


	protected function processNodeAttrs(Node $node, array $exportedNode): array
	{
		foreach ($this->processors as $processor) {
			$exportedNode = $processor->processNodeAttrs($node, $exportedNode);
		}
		return $exportedNode;
	}


	protected function processEdgeAttrs(Edge $edge, array $exportedEdge): array
	{
		foreach ($this->processors as $processor) {
			$exportedEdge = $processor->processEdgeAttrs($edge, $exportedEdge);
		}
		return $exportedEdge;
	}


	/**
	 * Debug: Dump plain text representation of the graph hierarchy
	 */
	public function dumpNodeTree(NestedGraph $graph = null, $indent = "", $withEdges = true): void
	{
		if ($graph === null) {
			$this->dumpNodeTree($this->graph);
			return;
		}

		if ($withEdges) {
			foreach ($graph->getEdges() as $edge) {
				echo $indent, "- ", $edge->getId(), ' (', $edge->getStart()->getId(), ' -> ', $edge->getEnd()->getId(), ")\n";
			}
		}

		foreach ($graph->getNodes() as $node) {
			echo $indent, "* ", $node->getId(), "\n";
			if ($node->hasNestedGraph()) {
				$this->dumpNodeTree($node->getNestedGraph(), $indent . "\t");
			}
		}
	}

	/**
	 * Export $graph to JSON array.
	 */
	private function exportNestedGraph(NestedGraph $graph): array
	{
		$nodes = [];
		foreach ($graph->getNodes() as $node) {
			$nodeJson = [
				'id' => $this->prefix . $node->getId(),
				'graph' => $node->hasNestedGraph() ? $this->exportNestedGraph($node->getNestedGraph()) : null,
				'attrs' => $this->processNodeAttrs($node, $node->getAttributes()),
			];
			if ($nodeJson !== null) {
				$nodes[] = $nodeJson;
			}
		}

		$edges = [];
		foreach ($graph->getEdges() as $edge) {
			$edgeJson = [
				'id' => $this->prefix . $edge->getId(),
				'start' => $this->prefix . $edge->getStart()->getId(),
				'end' => $this->prefix . $edge->getEnd()->getId(),
				'attrs' => $this->processEdgeAttrs($edge, $edge->getAttributes()),
			];
			if ($edgeJson !== null) {
				$edges[] = $edgeJson;
			}
		}

		return $this->processGraph($graph, [
			'layout' => 'dagre',
			'nodes' => $nodes,
			'edges' => $edges,
		]);
	}

}
