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
class JsonDirBackend extends AbstractBackend
{
	/**
	 * DI Container from which machines are obtained.
	 */
	protected $container;

	/**
	 * Name of directory which contains JSON files with state machine definitions.
	 */
	protected $base_dir;

	/**
	 * Static table of known machine types. Inherit this class and replace this
	 * table, or use 'machine_types' option when creating this backend.
	 *
	 * Each record is also passed to AbstractMachine::initializeMachine().
	 */
	protected $machine_type_table = array(
		/* '$machine_name' => array(
		 *     'table' => '$database_table',
		 *     'class' => '\SomeNamespace\Class',
		 *  	// Human-friendly name of the type
		 *  	'name' => 'Foo Bar',
		 *  	// Human-friendly description (one short paragraph, plain text)
		 *  	'desc' => 'Lorem ipsum dolor sit amet, ...',
		 *  	// Name of the file containing full machine definition
		 *  	'src'  => 'example/foo.json',
		 */
	);


	/**
	 * Constructor compatible with cascade resource factory.
	 *
	 * Options:
	 * 
	 *   - `machine_global_config`: Config wich will be merged into all state machines
	 *   - `cache_disabled`: Don't use caching
	 *   - `file_readers`: File readers map (regexp -> class name)
	 *
	 */
	public function __construct($options, \Psr\Container\ContainerInterface $container = null)
	{
		$this->container = $container;

		// Get base dir (constants are available)
		$this->base_dir = Utils::filename_format($options['base_dir'], array());

		// Load machine definitions from APC cache
		if (!empty($options['cache_disabled']) || !function_exists('apcu_fetch')) {
			$cache_loaded = false;
			$cache_mtime = 0;
			$cache_disabled = true;
		} else {
			$cache_disabled = false;
			$cache_key = get_class($this).':'.$this->base_dir;
			$cache_data = apcu_fetch($cache_key, $cache_loaded);
			if ($cache_loaded) {
				list($this->machine_type_table, $cache_mtime) = $cache_data;
				//debug_dump($this->machine_type_table, 'Cached @ '.strftime('%F %T', $cache_mtime));
			}
		}

		// Prepare configuration reader
		$config_reader = new JsonDirReader($this->base_dir, $options['file_readers'] ?? [], $options['machine_global_config'] ?? []);
		$config_reader->detectConfiguration();
		$latest_mtime = $config_reader->getLatestMTime();

		// Load data if cache is obsolete
		if (!$cache_loaded || $latest_mtime >= $cache_mtime) {
			//debug_msg('Machine type table cache miss. Reloading...');
			$this->machine_type_table = $config_reader->loadConfiguration();

			if (!$cache_disabled) {
				apcu_store($cache_key, array($this->machine_type_table, time()));
			}
		}
	}


	/**
	 * Creates a listing using given filters.
	 *
	 * @TODO: Support complex filtering over multiple machine types and make
	 * 	'type' filter optional.
	 *
	 * @see AbstractBackend::createListing()
	 * @return IListing
	 */
	protected function createListing(Smalldb $smalldb, $filters, $filtering_flags = 0)
	{
		$type = $filters['type'];
		$machine = $this->getMachine($smalldb, $type);
		if ($machine === null) {
			throw new InvalidArgumentException('Machine type "'.$type.'" not found.');
		}

		// Do not confuse machine-specific filtering
		unset($filters['type']);

		return $machine->createListing($filters, $filtering_flags);
	}


	/**
	 * Get all known state machine types.
	 *
	 * Returns array of strings.
	 */
	public function getKnownTypes()
	{
		return array_keys($this->machine_type_table);
	}


	/**
	 * Describe given type without creating an instance of related state
	 * machine. Intended as data source for user interface generators
	 * (menu, navigation, ...).
	 *
	 * Returns machine description as propery-value pairs in array. There
	 * are few well-known property names which should be used if possible.
	 * Any unknown properties will be ignored.
	 *
	 * array(
	 *  	// Human-friendly name of the type
	 *  	'name' => 'Foo Bar',
	 *  	// Human-friendly description (one short paragraph, plain text)
	 *  	'desc' => 'Lorem ipsum dolor sit amet, ...',
	 *  	// Name of the file containing full machine definition
	 *  	'src'  => 'example/foo.json',
	 *  	...
	 * )
	 */
	public function describeType($type)
	{
		return @ $this->machine_type_table[$type];
	}


	/**
	 * Infer type of referenced machine type using lookup table.
	 *
	 * Reference is pair: Table name + Primary key.
	 *
	 * Returns true if decoding is successful, $type and $id are set to
	 * decoded values. Otherwise returns false.
	 *
	 * Since references are global identifier, this method identifies the
	 * type of referenced machine. In simple cases it maps part of ref to
	 * type, in more complex scenarios it may ask database.
	 *
	 * In simple applications ref consists of pair $type and $id, where $id
	 * is uniquie within given $type.
	 *
	 * $aref is array of arguments passed to AbstractBackend::ref().
	 *
	 * $type is string.
	 *
	 * $id is literal or array of literals (in case of compound key).
	 */
	public function inferMachineType($aref, & $type, & $id)
	{
		$len = count($aref);

		if ($len == 0) {
			return false;
		}

		$type = str_replace('-', '_', array_shift($aref));

		if (!isset($this->machine_type_table[$type])) {
			return false;
		}

		switch ($len) {
			case 1: $id = null; break;
			case 2: $id = reset($aref); break;
			default: $id = $aref;
		}

		return true;
	}


	/**
	 * Factory method: Prepare state machine of given type - a model shared
	 * between multiple real statemachines stored in backend. Do not forget
	 * that actual machine is not reachable, you only get this interface.
	 *
	 * This creates only implementation of the machine, not concrete
	 * instance. See AbstractMachine.
	 *
	 * Returns descendant of AbstractMachine or null.
	 */
	protected function createMachine(Smalldb $smalldb, $type)
	{
		if (isset($this->machine_type_table[$type])) {
			$desc = $this->machine_type_table[$type];
		} else {
			return null;
		}

		if (empty($desc['class'])) {
			throw new InvalidArgumentException('Class not specified in machine configuration.');
		}

		$m = null;
		if ($this->container && $this->container instanceof \Psr\Container\ContainerInterface && $this->container->has($desc['class'])) {
			$m = $this->container->get($desc['class']);
		}
		if ($m === null) {
			//debug_msg('Creating machine %s from class: %s', $type, $desc['class']);
			$m = new $desc['class']();
		}

		$m->initializeMachine($smalldb, $type, $desc);
		return $m;
	}

}

