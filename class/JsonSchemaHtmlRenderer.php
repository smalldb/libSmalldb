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
		return new self(static::parseFile($filename));
	}


	/**
	 * Read and process file, read dependencies if exist.
	 */
	public static function parseFile($filename)
	{
		$schema = \Smalldb\StateMachine\Utils::parse_json_file($filename);
		$extends_file = isset($schema['extends_file']) ? dirname($filename).'/'.$schema['extends_file'] : null;
		while ($extends_file !== null) {
			$part = \Smalldb\StateMachine\Utils::parse_json_file($extends_file);
			$extends_file = isset($part['extends_file']) ? dirname($extends_file).'/'.$part['extends_file'] : null;
			$schema = static::extendSchema($part, $schema);
		}
		if (isset($schema['extends_file'])) {
			unset($schema['extends_file']);
		}
		return $schema;
	}


	/**
	 * Recursively merge two schemas extending $orig with $ext.
	 *
	 * Like array_replace_recursive, but it concatenates lists instead of
	 * overwriting numeric keys.
	 *
	 * @param $orig Parent config.
	 * @param $ext Child config which extends the $parent.
	 */
	protected static function extendSchema($orig, $ext)
	{
		if (is_array($orig) && is_array($ext)) {
			foreach ($ext as $ext_k => $ext_v) {
				if (is_numeric($ext_k)) {
					array_push($orig, $ext_v);
				} else if (isset($orig[$ext_k])){
					$orig[$ext_k] = static::extendSchema($orig[$ext_k], $ext_v);
					$orig['_src'][$ext_k] = $ext_src;
				} else {
					$orig[$ext_k] = $ext_v;
				}
			}
			return $orig;
		} else {
			return $ext;
		}
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

		echo "<li class=\"json_schema_node\" data-path=\"", htmlspecialchars($path), "\">\n";

		/*
		if ($expandable) {
			echo "<input type=\"checkbox\" class=\"json_schema_node_toggle\"", $depth > 2 ? ' checked' : '', ">\n";
		}
		// */

		echo "<div class=\"json_schema_node_title\">\n";
		if ($node_name != '') {
			echo '<b class="json_schema_node_name">', htmlspecialchars($node_name), ":</b>\n";
		}
		if ($node === false) {
			echo '<span class="json_schema_node_type">[not allowed]</span>', "\n";
		}
		if (isset($node['title'])) {
			echo "<em class=\"json_schema_node_description\">", htmlspecialchars($node['title']), "</em>\n";
		}
		if (isset($node['description'])) {
			echo "<span class=\"json_schema_node_description\">", htmlspecialchars($node['description']), "</span>\n";
		}
		if (isset($node['type'])) {
			echo '<span class="json_schema_node_type">[', htmlspecialchars(join(', ', $type)), ']</span>', "\n";
		} else {
			$type = [];
		}
		if ($is_required) {
			echo '<span class="json_schema_node_is_required">[required]</span>', "\n";
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

