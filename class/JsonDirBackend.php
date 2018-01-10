<?php
/*
 * Copyright (c) 2013-2016, Josef Kufner  <josef@kufner.cz>
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

use Psr\Container\ContainerInterface;
use Smalldb\StateMachine\Utils\Utils;


/**
 * Smalldb Backend which loads state machine definitions from a directory full 
 * of JSON files and other files included by the JSON files; each JSON file
 * defines a one state machine type.
 *
 * JsonDirBackend supports following file types:
 *
 *   - JSON: Raw structure loaded as is. See configuration schema sections in
 *     AbstractMachine and derived classes. Each of these files represents one
 *     state machine type and can include other files.
 *       - Extensions: `.json`, `.json.php`
 *   - GraphML: Graph created by yEd graph editor. See GraphMLReader.
 *       - Extensions: `.graphml`
 *   - BPMN: Process diagrams in standard BPMN XML file. See BpmnReader.
 *       - Extensions: `.bpmn`
 *
 */
class JsonDirBackend extends SimpleBackend
{

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface $container  Container from which to instantiate state machines.
	 * @param array $config  Backend configuration.
	 *
	 * Options:
	 * 
	 *   - `machine_global_config`: Config wich will be merged into all state machines
	 *   - `cache_disabled`: Don't use caching
	 *   - `file_readers`: File readers map (regexp -> class name)
	 *
	 */
	public function initializeBackend(array $config)
	{
		parent::initializeBackend($config);

		// Get base dir (constants are available)
		$base_dir = Utils::filename_format($config['base_dir'], array());

		$machine_type_table = null;

		// Load machine definitions from APC cache
		if (!empty($config['cache_disabled']) || !function_exists('apcu_fetch')) {
			$cache_disabled = true;
			$cache_loaded = false;
			$cache_mtime = 0;
		} else {
			$cache_disabled = false;
			$cache_key = get_class($this).':'.$base_dir;
			$cache_data = apcu_fetch($cache_key, $cache_loaded);
			if ($cache_loaded) {
				list($machine_type_table, $cache_mtime) = $cache_data;
				//debug_dump($this->machine_type_table, 'Cached @ '.strftime('%F %T', $cache_mtime));
			} else {
				$cache_mtime = 0;
			}
		}

		// Prepare configuration reader
		$config_reader = new JsonDirReader($base_dir, $config['file_readers'] ?? [], $config['machine_global_config'] ?? []);
		$config_reader->detectConfiguration();
		$latest_mtime = $config_reader->getLatestMTime();

		// Load data if cache is obsolete
		if (!$cache_loaded || $latest_mtime >= $cache_mtime) {
			//debug_msg('Machine type table cache miss. Reloading...');
			$machine_type_table = $config_reader->loadConfiguration();

			if (!$cache_disabled) {
				apcu_store($cache_key, array($machine_type_table, $latest_mtime));
			}
		}

		if ($machine_type_table) {
			$this->registerAllMachineTypes($machine_type_table);
		} else {
			throw new RuntimeException("Caching logic failed when loading backend configuration.");
		}
	}

}

