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
	protected $file_readers;
	protected $postprocess_list;
	protected $machine_global_config;

	/**
	 * Constructor.
	 *
	 * @param string $base_dir Directory where JSON files are located.
	 * @param array $custom_file_readers Map file name regexp => file loader class name.
	 * @param array $machine_global_config  Configuration added to each machine configuration.
	 */
	public function __construct(string $base_dir, array $custom_file_readers = [], array $machine_global_config = [])
	{
		$this->base_dir = rtrim($base_dir, '/').'/';

		// File readers
		// TODO: Inject readers from DI container
		$file_readers = [
			'/\.json\(\.php\)\?$/i' => JsonReader::class,
			'/\.graphml$/i' => GraphMLReader::class,
			'/\.bpmn$/i' => BpmnReader::class,
		];
		$this->file_readers = empty($custom_file_readers) ? $file_readers : array_merge($file_readers, $custom_file_readers);
		$this->postprocess_list = array_fill_keys(array_reverse($file_readers), false);

		$this->machine_global_config = $machine_global_config;
	}


	/**
	 * Scan for the configuration and build list of files, but do not load the files.
	 */
	public function detectConfiguration()
	{
		// Scan base dir for machines and find youngest file
		$dh = opendir($this->base_dir);
		if (!$dh) {
			throw new RuntimeException('Cannot open base dir: '.$this->base_dir);
		}
		$this->file_list = [];
		while (($file = readdir($dh)) !== false) {
			if ($file[0] != '.') {
				$this->file_list[] = $file;
			}
		}
		closedir($dh);
	}


	/**
	 * @return int Modification time of the most recently changed config file found.
	 */
	public function getLatestMTime(): int
	{
		if ($this->file_list === null) {
			$this->detectConfiguration();
		}

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
		if ($this->file_list === null) {
			$this->detectConfiguration();
		}

		$machine_type_table = [];

		// Find all machine types
		foreach ($this->file_list as $file) {
			@ list($machine_type, $ext) = explode('.', $file, 2);
			switch ($ext) {
				case 'json':
				case 'json.php':
					$filename = $this->base_dir.$file;
					$machine_type_table[$machine_type] = JsonReader::loadString($machine_type, file_get_contents($filename), [], $filename);
					$this->postprocess_list[JsonReader::class] = true;
					break;
			}
		}
		ksort($machine_type_table);

		// Load all included files
		// TODO: Recursively process includes of includes
		foreach ($machine_type_table as $machine_type => $json_config) {
			$machine_def = $machine_type_table[$machine_type];
			$machine_success = true;
			$errors = [];

			foreach ((array) ($json_config['include'] ?? []) as $include) {
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

				// Detect file type and use proper loader
				$reader_matched = false;
				foreach ($this->file_readers as $pattern => $reader) {
					if (preg_match($pattern, $include_file)) {
						$machine_def = array_replace_recursive(
							$reader::loadString($machine_type, file_get_contents($include_file), $include_opts, $include_file),
							$machine_def);

						$reader_matched = true;
						$this->postprocess_list[$reader] = true;
						break;
					}
				}
				if (!$reader_matched) {
					throw new RuntimeException('Unknown file format: '.$include_file);
				}
			}

			// Run post-processing of each reader
			foreach ($this->postprocess_list as $reader => $used) {
				if ($used) {
					$machine_success &= $reader::postprocessDefinition($machine_type, $machine_def, $errors);
				}
			}

			// Add global defaults (don't override)
			$machine_def = array_replace_recursive($machine_def, $this->machine_global_config);

			// Store errors in definition
			// TODO: Replace simple array with a ErrorListener which preserves context of the error.
			$machine_def['errors'] = $errors;

			// TODO: Use $machine_machine_success somehow?

			$machine_type_table[$machine_type] = $machine_def;
		}

		return $machine_type_table;
	}

}
