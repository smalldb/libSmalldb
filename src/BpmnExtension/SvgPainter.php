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

use Smalldb\Graph\Graph;
use Smalldb\Graph\Node;


class SvgPainter
{

	public function colorizeSvgFile(string $svgFileContent, Graph $bpmnGraph, ?string $participantId, array $errors, string $prefix): string
	{
		$svg_style = '';
		$processor = new GrafovatkoProcessor();
		$processor->setPrefix($prefix);
		$processor->setTargetParticipant($participantId);

		// Style target participant
		if ($participantId !== null) {
			$svg_style .= "djs-element[data-element-id=$participantId] .djs-visual > rect {"
				. " fill: #ffe !important;"
				. " }\n";
		}

		// Style nodes
		foreach ($bpmnGraph->getAllNodes() as $id => $node) {
			$nodeAttrs = $processor->processNodeAttrs($node, []);

			// Don't style annotations
			if (isset($nodeAttrs['shape']) && $nodeAttrs['shape'] == 'note') {
				continue;
			}
			// Top-level shape
			$svg_style .= ".djs-element[data-element-id=$id] .djs-visual > *:first-child {";
			if (isset($nodeAttrs['fill'])) {
				$svg_style .= " fill:" . $nodeAttrs['fill'] . " !important;";
			}
			$svg_style .= " }\n";
			// All shapes
			$svg_style .= ".djs-element[data-element-id=$id] .djs-visual > * {";
			if (isset($nodeAttrs['color'])) {
				$svg_style .= " stroke:" . $nodeAttrs['color'] . " !important;";
			}
			$svg_style .= " }\n";
		}

		// Style arrows
		foreach ($bpmnGraph->getAllEdges() as $id => $edge) {
			$edgeAttrs = $processor->processEdgeAttrs($edge, []);

			$svg_style .= ".djs-element[data-element-id=$id] .djs-visual * {";
			if (isset($edgeAttrs['color'])) {
				$svg_style .= " stroke:" . $edgeAttrs['color'] . " !important;";
			}
			$svg_style .= " }\n";
		}

		// Close styles
		$svg_style .= ".djs-element .djs-visual text, .djs-element .djs-visual text * { stroke: none !important; }\n";

		// Render errors (somehow)
		foreach ($errors as $err) {
			foreach ($err['nodes'] as $n) {
				/** @var Node $n */
				$n_id = $n->getId();
				$svg_style .= ".djs-element[data-element-id=$n_id] > .djs-outline {"
					. " fill: rgba(255, 0, 0, 0.05) !important;"
					. " stroke: #f00 !important;"
					. " stroke-width: 2 !important;"
					. "}\n";
			}
		}

		// Gradient definitions
		//$svg_def_el = $processor->getExtraSvgElements($bpmnGraph);
		$svg_def_el = '<defs>'
			. '<linearGradient id="' . $prefix . '_gradient_rcv_inv">'
			. '<stop offset="50%" stop-color="#ff8" />'
			. '<stop offset="50%" stop-color="#adf" />'
			. '</linearGradient>'
			. '<linearGradient id="' . $prefix . '_gradient_pos_rcv">'
			. '<stop offset="50%" stop-color="#fff" />'
			. '<stop offset="50%" stop-color="#adf" />'
			. '</linearGradient>'
			. '</defs>';

		// Build style element
		$svg_style_el = "<style type=\"text/css\">" . htmlspecialchars($svg_style) . "</style>";
		$svg_end_pos = strrpos($svgFileContent, '</svg>');
		$svg_contents_with_style = substr_replace($svgFileContent, $svg_style_el . $svg_def_el, $svg_end_pos, 0);
		return $svg_contents_with_style;
	}

}
