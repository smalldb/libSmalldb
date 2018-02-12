<?php
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


namespace Smalldb\StateMachine;


class JsonDirReader
{

	protected $base_dir;
	protected $file_list = null;
	protected $file_readers = [];
	protected $machine_global_config;

	/**
	 * Constructor.
	 *
	 * @param string $base_dir Directory where JSON files are located.
	 * @param array $machine_global_config  Configuration added to each machine configuration.
	 */
	public function __construct(string $base_dir, array $machine_global_config = [])
	{
		$this->base_dir = rtrim($base_dir, '/').'/';
		$this->machine_global_config = $machine_global_config;
	}


	public function registerFileReader(IMachineDefinitionReader $file_reader): self
	{
		$this->file_readers[] = $file_reader;
		return $this;
	}


	/**
	 * Scan for the configuration and build list of files, but do not load the files.
	 *
	 * This method may be called many times, but it will detect the configuration only once.
	 */
	public function detectConfiguration()
	{
		// Use default file readers if none provided
		if (empty($this->file_readers)) {
			$this->file_readers = [
				new JsonReader(),
				new GraphMLReader(),
				new BpmnReader(),
			];
		}

		// Scan base dir for machines and find youngest file
		if ($this->file_list === null) {
			$dh = opendir($this->base_dir);
			if (!$dh) {
				throw new RuntimeException('Cannot open base dir: ' . $this->base_dir);
			}
			$this->file_list = [];
			while (($file = readdir($dh)) !== false) {
				if ($file[0] != '.') {
					$this->file_list[] = $file;
				}
			}
			closedir($dh);
		}
	}


	/**
	 * @return int Modification time of the most recently changed config file found.
	 */
	public function getLatestMTime(): int
	{
		$this->detectConfiguration();

		$latest_mtime = filemtime(__FILE__);  // Make sure cache gets regenerated when this file is changed

		foreach ($this->file_list as $file) {
			$mtime = filemtime($this->base_dir . $file);
			if ($latest_mtime < $mtime) {
				$latest_mtime = $mtime;
			}
		}

		return $latest_mtime;
	}


	/**
	 * Load the configuration from the JSON files.
	 */
	public function loadConfiguration(): array
	{
		$this->detectConfiguration();

		$machine_type_table = [];

		// Find all machine types
		foreach ($this->file_list as $file) {
			// Load all JSON files
			if (preg_match('/^(.*)\.json(\.php)?$/i', $file, $m)) {
				$machine_type = $m[1];
				$file_name = $this->base_dir.$file;

				$machine_def = $this->parseFile($machine_type, $file_name, []);
				$includes = $machine_def['include'] ?? [];
				// TODO: Recursively process includes of includes

				$machine_success = true;
				$errors = [];
				$postprocess_list = []; /** @var IMachineDefinitionReader[] $postprocess_list*/

				// Load files using readers
				foreach ($includes as $include) {
					// String is filename
					if (!is_array($include)) {
						$include_file = $include;
						$include_opts = [];
					} else {
						$include_file = $include['file'];
						$include_opts = $include;
					}

					// Relative path is relative to base directory
					if ($include_file[0] != '/') {
						$include_file = $this->base_dir.$include_file;
					}

					$reader = null;
					$parsed_content = $this->parseFile($machine_type, $include_file, $include_opts, $reader);
					$machine_def = array_replace_recursive($parsed_content, $machine_def);

					if ($reader) {
						$postprocess_list[spl_object_hash($reader)] = $reader;
					}
				}

				// Postprocess the definition by the readers (each reader gets called only once)
				foreach ($postprocess_list as $postprocessor) {
					$postprocessor->postprocessDefinition($machine_type, $machine_def, $errors);
				}

				if (!empty($errors)) {
					// TODO: Replace simple array with a ErrorListener which preserves context of the error.
					$machine_def['errors'] = $errors;
				}

				// Add global defaults (don't override)
				$machine_def = array_replace_recursive($machine_def, $this->machine_global_config);

				// Store the final machine definition
				$machine_type_table[$machine_type] = $machine_def;
			}
		}

		// Sort machines by name
		ksort($machine_type_table);

		return $machine_type_table;
	}


	private function parseFile(string $machine_type, string $file_name, array $include_opts, IMachineDefinitionReader & $used_reader = null): array
	{
		foreach ($this->file_readers as $pattern => $reader) {
			$file_extension = '.' . pathinfo($file_name, PATHINFO_EXTENSION);

			if ($reader->isSupported($file_extension)) {
				$file_contents = file_get_contents($file_name);
				$parsed_content = $reader->loadString($machine_type, $file_contents, $include_opts, $file_name);
				$used_reader = $reader;
				return $parsed_content;
			}
		}
		throw new RuntimeException('Unknown file format: '.$file_name);
	}

}
