<?php
/*
 * Copyright (c) 2016, Josef Kufner  <josef@kufner.cz>
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
namespace Smalldb\StateMachine;

/**
 * Render JSON schema into HTML elements
 */
class JsonSchemaHtmlRenderer
{
	/// Nested arrays representation of the schema
	protected $schema;


	/**
	 * Constructor.
	 */
	public function __construct($schema)
	{
		$this->schema = $schema;
	}


	/**
	 * Load file and create the renderer.
	 */
	public static function loadFile($filename)
	{
		return new self(JsonSchemaReader::loadFile($filename)->schema);
	}


	/**
	 * Render to string
	 */
	public function __toString()
	{
		return $this->render();
	}

	/**
	 * Render schema to HTML
	 */
	public function render()
	{
		ob_start();
		echo "<ul>\n";
		$this->renderNode(null, $this->schema);
		echo "</ul>\n";
		return ob_get_clean();
	}

	private function renderNode($node_name, $node, $parent = null, $path = '', $depth = 0)
	{
		$type = isset($node['type']) ? (array) $node['type'] : [];
		$expandable = in_array('object', $type);
		$is_required = ($parent !== null && isset($parent['required']) && in_array($node_name, $parent['required']));

		echo "<li class=\"json_schema_node", $node === false ? '' : (!empty($node['_annotation']) ? ' json_schema_node_annotated':' json_schema_node_not_annotated'), "\"",
			" data-path=\"", htmlspecialchars($path), "\">\n";


		echo "<div class=\"json_schema_node_title\">\n";
		if ($node_name != '') {
			echo '<b class="json_schema_node_name">', htmlspecialchars($node_name), ":</b>\n";
		}
		if ($node === false) {
			echo '<span class="json_schema_node_type json_schema_node_not_allowed">[not allowed]</span>', "\n";
		}
		if (isset($node['title'])) {
			echo "<em class=\"json_schema_node_description\">", htmlspecialchars($node['title']), "</em>\n";
		}
		if (!empty($node['_annotation'])) {
			echo '<span class="json_schema_node_annotation">[', htmlspecialchars($node['_annotation']), ']</span>', "\n";
		}
		if (isset($node['type'])) {
			echo '<span class="json_schema_node_type">[', htmlspecialchars(join(', ', $type)), ']</span>', "\n";
		} else {
			$type = [];
		}
		if ($is_required) {
			echo '<span class="json_schema_node_is_required">[required]</span>', "\n";
		}
		if (isset($node['description'])) {
			echo "<span class=\"json_schema_node_description\">", htmlspecialchars($node['description']), "</span>\n";
		}
		echo "</div>\n";

		echo "<div class=\"json_schema_node_details\">\n";


		$simple_options = [
			'pattern' => 'Pattern',
			'format' => 'Format',
			'maxLength' => 'Max length',
			'minLength' => 'Min length',
			'multipleOf' => 'Multiple of',
			'maximum' => 'Maximum',
			'exclusiveMaximum' => 'Maximum is excluded',
			'minimum' => 'Minimum',
			'exclusiveMinimum' => 'Minimum is excluded',
		];
		foreach ($simple_options as $k => $label) {
			if (isset($node[$k])) {
				echo "<div class=\"json_schema_node_", $k, "\">", $label, ': <code>', htmlspecialchars($node[$k]), "</code></div>\n";
			}
		}

		if (isset($node['enum'])) {
			echo "<div class=\"json_schema_node_enum\">Enum: <code>",
				htmlspecialchars(json_encode($node['enum'], JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
				"</code></div>\n";
		}

		if (isset($node['default'])) {
			echo "<div class=\"json_schema_node_default\">Default: <code>",
				htmlspecialchars(json_encode($node['default'], JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
				"</code></div>\n";
		}

		if (in_array('object', $type)) {
			if (isset($node['properties'])) {
				ksort($node['properties']);
				echo "<ul>\n";
				foreach ($node['properties'] as $child_name => $child) {
					$this->renderNode($child_name, $child, $node, $path != '' ? $path.'.'.$child_name : $child_name, $depth + 1);
				}
				echo "</ul>\n";
			}
			if (isset($node['patternProperties'])) {
				ksort($node['patternProperties']);
				echo "<ul>\n";
				foreach ($node['patternProperties'] as $child_name => $child) {
					$this->renderNode($child_name, $child, $node, $path != '' ? $path.'.'.$child_name : $child_name, $depth + 1);
				}
				echo "</ul>\n";
			}
			if (isset($node['additionalProperties'])) {
				echo "<ul>\n";
				$this->renderNode('*', $node['additionalProperties'], $path, $depth + 1);
				echo "</ul>\n";
			}
		}

		if (in_array('array', $type)) {
			if (isset($node['items'])) {
				echo "<ul>\n";
				$this->renderNode('*', $node['items'], $path, $depth + 1);
				echo "</ul>\n";
			}
		}

		echo "</div>\n";

		echo "</li>\n";
	}

}

