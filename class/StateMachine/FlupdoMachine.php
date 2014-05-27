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

/**
 * Base class for state machines accessed via Flupdo.
 */
abstract class FlupdoMachine extends AbstractMachine
{
	/**
	 * Database connection.
	 */
	protected $flupdo;

	/**
	 * Name of SQL table, where machine properties are stored.
	 */
	protected $table;

	/**
	 * List of columns which are used as primary key.
	 */
	protected $pk_columns = null;

	/**
	 * Column containing entity owner.
	 */
	protected $user_id_table_column = null;

	/**
	 * Auth object method name to retrieve current user ID.
	 *
	 * TODO: Review this.
	 */
	protected $user_id_auth_method = null;


	/**
	 * True if state should not be loaded with properties.
	 */
	protected $load_state_with_properties = true;


	/**
	 * Define state machine used by all instances of this type.
	 */
	protected function initializeMachine($config)
	{
		$flupdo_resource_name = @ $config['flupdo_resource'];
		if ($flupdo_resource_name == null) {
			$flupdo_resource_name = 'database';
		}
		$this->flupdo = $this->context->$flupdo_resource_name;

		if (!($this->flupdo instanceof \Smalldb\Flupdo\Flupdo)) {
			throw new InvalidArgumentException('Flupdo resource is not an instance of \\Smalldb\\Flupdo\\Flupdo.');
		}
	}


	/**
	 * Returns true if user has required permissions.
	 */
	protected function checkPermissions($permissions, $id)
	{
		// Check owner
		if (@ $permissions['owner'] && $this->user_id_table_column && ($a = $this->user_id_auth_method)) {
			$properties = $this->getProperties();
			if ($properties[$this->user_id_table_column] == $this->backend->getAuth()->$a()) {
				return true;
			} else {
				return false;
			}
		}

		return true;
	}


	/**
	 * Adds conditions to enforce read permissions to query object.
	 */
	protected function addPermissionsCondition($query)
	{
		if ($this->user_id_table_column && ($a = $this->user_id_auth_method)) {
			$query->where('`'.$this->flupdo->quoteIdent($this->user_id_table_column).'` = ?', $this->backend->getAuth()->$a());
		}
	}


	/**
	 * Low level API for querying underlaying database. It is 
	 * implementation specific and should not be used. However, itis better 
	 * to have one specified shortcut than many ugly hacks.
	 */
	public function createQueryBuilder()
	{
		// FIXME: This should not be here. There should be generic 
		// listing API and separate listing class.

		$q = $this->flupdo->select();
		$this->queryAddFrom($q);
		return $q;
	}


	/**
	 * Create generic listing on this machine type
	 *
	 * TODO: Fix this.
	 */
	public function createListing($filters)
	{
		$listing = new \Smalldb\StateMachine\FlupdoGenericListing($this->flupdo);
		$this->queryAddFrom($listing);
		$this->queryAddStateSelect($listing);
		$this->queryAddPropertiesSelect($listing);
		$this->addPermissionsCondition($listing);
		$listing->debugDump();
		return $listing;
	}


	/**
	 * Add FROM clause
	 */
	protected function queryAddFrom($query)
	{
		$query->from($query->quoteIdent($this->table));
	}


	/**
	 * Add state column into select clause of the $query.
	 *
	 * Must add only one column.
	 */
	abstract protected function queryAddStateSelect($query);


	/**
	 * Add properties to select.
	 */
	protected function queryAddPropertiesSelect($query)
	{
		$query->select($query->quoteIdent($this->table).'.*');
	}


	/**
	 * Add primary key condition to where clause. Result should contain
	 * only one row now.
	 *
	 * Returns $query.
	 */
	protected function queryAddPrimaryKeyWhere($query, $id)
	{
		if ($id === null || $id === array() || $id === false || $id === '') {
			throw new InvalidArgumentException('Empty ID.');
		} else if (count($id) != count($this->describeId())) {
			throw new InvalidArgumentException('Malformed ID.');
		}
		foreach (array_combine($this->describeId(), (array) $id) as $col => $val) {
			$query->where($query->quoteIdent($col).' = ?', $val);
		}
		return $query;
	}


	/**
	 * Get current state of state machine.
	 */
	public function getState($id)
	{
		if ($id === null || $id === array()) {
			return '';
		}

		$q = $this->createQueryBuilder()
			->select(null)
			->limit(1);

		$this->queryAddStateSelect($q);
		$this->queryAddPrimaryKeyWhere($q, $id);

		$r = $q->query();
		$state = $r->fetchColumn(0);
		$r->closeCursor();

		return (string) $state;
	}


	/**
	 * Get all properties of state machine, including it's state.
	 */
	public function getProperties($id, & $state_cache = null)
	{
		if ($id === null || $id === array()) {
			throw new RuntimeException('State machine instance does not exist.');
		}

		$q = $this->createQueryBuilder()
			->select(null)
			->limit(1);

		$this->queryAddPropertiesSelect($q);
		$this->queryAddPrimaryKeyWhere($q, $id);

		if ($this->load_state_with_properties) {
			$this->queryAddStateSelect($q);
		}

		$r = $q->query();
		$props = $r->fetch(\PDO::FETCH_ASSOC);
		$r->closeCursor();

		if ($props === null) {
			throw new RuntimeException('State machine instance not found.');
		}

		if ($this->load_state_with_properties) {
			$state_cache = array_pop($props);
		}

		return $props;
	}


	/**
	 * Reflection: Describe ID (primary key).
	 *
	 * Returns array of all parts of the primary key and its
	 * types (as strings). If primary key is not compound, something
	 * like array('id') is returned.
	 *
	 * Order of the parts may be mandatory.
	 */
	public function describeId()
	{
		if ($this->pk_columns !== null) {
			return $this->pk_columns;
		}

		$this->pk_columns = array();

		$r = $this->flupdo->query('SHOW KEYS FROM '.$this->flupdo->quoteIdent($this->table).' WHERE Key_name = "PRIMARY"');

		while (($row = $r->fetch(\PDO::FETCH_ASSOC)) !== FALSE) {
			$this->pk_columns[] = $row['Column_name'];
		}

		return $this->pk_columns;
	}

}

