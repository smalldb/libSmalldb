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

use Smalldb\StateMachine\Utils\Utils;


/**
 * Load JSON schema from file and interpret `extends_file` directive.
 */
class JsonSchemaReader
{
	/// Name of loaded file
	public $filename;

	/// Nested arrays representation of the schema
	public $schema;

	/// List of extended files (parents)
	public $extendedFiles = [];

	/**
	 * Constructor.
	 */
	public function __construct($filename)
	{
		$this->filename = $filename;
		$this->schema = $this->parseFile($filename, $this->extendedFiles);
	}


	/**
	 * Load file and create the renderer.
	 */
	public static function loadFile($filename)
	{
		return new self($filename);
	}


	/**
	 * Read and process file, read dependencies if exist.
	 *
	 * @param[in] $filename Name of the file to parse and interpet.
	 * @param[out] $extendedFiles List of files additionaly loaded via `extends_file` directive.
	 */
	public static function parseFile($filename, & $extendedFiles = null)
	{
		$schema = static::annotateSchema(Utils::parse_json_file($filename), false);
		$extends_file = isset($schema['extends_file']) ? dirname($filename).'/'.$schema['extends_file'] : null;
		while ($extends_file !== null) {
			if ($extendedFiles !== null) {
				$extendedFiles[] = $extends_file;
			}
			$part = static::annotateSchema(Utils::parse_json_file($extends_file),
					str_replace('.schema.json', '', basename($extends_file)));
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
	 * Annotate schema with an annotation.
	 */
	protected static function annotateSchema($schema, $annotation, $path = '')
	{
		if (isset($schema['type']) && !isset($schema['_annotation'])) {
			$schema['_annotation'] = $annotation;
		}

		foreach (['properties', 'patternProperties'] as $p) {
			if (isset($schema[$p])) {
				foreach($schema[$p] as $k => & $v) {
					$v = static::annotateSchema($v, $annotation, $path.'/'.$p.'/'.$k);
				}
			}
		}

		foreach (['items', 'additionalProperties'] as $p) {
			if (isset($schema[$p])) {
				$schema[$p] = static::annotateSchema($schema[$p], $annotation, $path.'/'.$p);
			}
		}

		return $schema;
	}


	/**
	 * Render to JSON string, exporting fully expanded JSON schema
	 */
	public function __toString()
	{
		return json_encode($this->schema, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}

}

