<?php
/*
 * Copyright (c) 2013, Josef Kufner  <jk@frozen-doe.net>
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

use	\Smalldb\Flupdo\Flupdo,
	\Smalldb\Flupdo\FlupdoProxy;

/**
 * Smalldb Backend which loads state machine definitions from a directory full 
 * of JSON files.
 *
 * TODO: Caching!
 */
class JsonDirBackend extends AbstractBackend
{
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


	public function __construct($options, $context, $alias)
	{
		parent::__construct($options, $context, $alias);

		// Get base dir
		$this->base_dir = filename_format($options['base_dir']);

		// Scan base dir for machines
		// TODO: Use APC cache!
		$dh = opendir($this->base_dir);
		if (!$dh) {
			throw new RuntimeException('Cannot open base dir: '.$this->base_dir);
		}
		while (($file = readdir($dh)) !== false) {
			if (preg_match('/^([^.].*)\.json\.php$/', $file, $m)) {
				$type = $m[1];
				$this->machine_type_table[$type] = parse_json_file($this->base_dir.$file);
			}
		}
		closedir($dh);
	}


	/**
	 * Creates a listing using given filters.
	 */
	public function createListing($filters)
	{
		$type = $filters['type'];
		return $this->getMachine($type)->createListing($filters);
	}



	// FIXME: This should be machine listing, not general query builder.
	public function createQueryBuilder($type)
	{
		return $this->getMachine($type)->createQueryBuilder();
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

		$type = array_shift($aref);

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
	protected function createMachine($type)
	{
		$desc = @ $this->machine_type_table[$type];
		if ($desc === null || empty($desc['class'])) {
			return null;
		}

		return new $desc['class']($this, $type, $desc, $this->getContext());
	}

}

