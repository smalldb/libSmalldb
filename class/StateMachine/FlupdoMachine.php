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
		// Get flupdo resource
		$flupdo_resource_name = @ $config['flupdo_resource'];
		if ($flupdo_resource_name == null) {
			$flupdo_resource_name = 'database';
		}
		$this->flupdo = $this->context->$flupdo_resource_name;
		if (!($this->flupdo instanceof \Smalldb\Flupdo\Flupdo)) {
			throw new InvalidArgumentException('Flupdo resource is not an instance of \\Smalldb\\Flupdo\\Flupdo.');
		}

		// Use config if not specified otherwise
		if ($this->table === null && isset($config['table'])) {
			$this->table = (string) $config['table'];
		}
		if ($this->states === null && isset($config['states'])) {
			$this->states = $config['states'];
		}
		if ($this->actions === null && isset($config['actions'])) {
			$this->actions = $config['actions'];
		}
		if ($this->pk_columns === null && isset($config['pk_columns'])) {
			$this->pk_columns = $config['pk_columns'];
		}
		if ($this->properties === null && isset($config['properties'])) {
			$this->properties = $config['properties'];
		}
		if ($this->state_groups === null && isset($config['state_groups'])) {
			$this->state_groups = $config['state_groups'];
		}
		if ($this->filters === null && isset($config['filters'])) {
			$this->filters = $config['filters'];
		}

		// Scan database for properties if not specified
		if (empty($this->properties)) {
			$this->scanTableColumns();
		} else if ($this->pk_columns === null) {
			// Collect primary keys if not specified
			$this->pk_columns = array();
			foreach ($this->properties as $property => $p) {
				if (!empty($p['is_pk'])) {
					$this->pk_columns[] = $property;
				}
			}
		}
	}


	/**
	 * Scan table in database and populate properties and pk_columns arrays.
	 */
	protected function scanTableColumns()
	{
		$r = $this->flupdo->select('*')
			->from($this->flupdo->quoteIdent($this->table))
			->where('FALSE')->limit(0)
			->query();
		$col_cnt = $r->columnCount();

		// build properties description
		$this->properties = array();
		$this->pk_columns = array();
		for ($i = 0; $i < $col_cnt; $i++) {
			$cm = $r->getColumnMeta($i);
			$this->properties[$cm['name']] = array(
				'name' => $cm['name'],
				'type' => $cm['native_type'], // FIXME: Do not include corrupted information, but at least something.
			);
			if (in_array('primary_key', $cm['flags'])) {
				$this->pk_columns[] = $cm['name'];
			}
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
	 * Create generic listing on this machine type.
	 *
	 * ### Filter syntax
	 *
	 * Syntax is designed for use in query part of URL. First, filter name
	 * is looked up in statemachine filters. If not found, filter name is
	 * considered a property name and if matches, equality condition is
	 * added. Otherwise if operator is detected at the end of property
	 * name, given condition is added.
	 *
	 * Please keep in mind that there is '=' character in URL between
	 * filter name and value. For example '<' filter looks like
	 * `...?property<=value`.
	 *
	 * Conditions:
	 *
	 *   - `!=` means "not equal".
	 *   - `<` means "lesser or equal".
	 *   - `<<`  means "less than".
	 *   - `>`  means "greater or equal".
	 *   - `>>`  means "greater than".
	 *   - `:` is range check. The value must be in format `min..max` or
	 *     `min...max`. Two dots means min <= x < max. Three or more dots
	 *     include max (min <= x <= max; using BETWEEN operator). The value
	 *     may be also an array of two elements or with 'min' and 'max'
	 *     keys.
	 *   - `~` is REGEXP operator.
	 *   - `%` is LIKE operator.
	 *   - `!:`, `!~` and `!%` are negated variants of previous three
	 *     operators.
	 */
	public function createListing($filters)
	{
		$listing = new \Smalldb\StateMachine\FlupdoGenericListing($this, $this->flupdo);

		// Prepare common select
		$query = $listing->getQueryBuilder();
		$this->queryAddFrom($query);
		$this->queryAddStateSelect($query);
		$this->queryAddPropertiesSelect($query);
		$this->addPermissionsCondition($query);

		// Limit & offset
		if (isset($filters['limit']) && !isset($this->filters['limit'])) {
			$query->limit((int) $filters['limit']);
		}
		if (isset($filters['offset']) && !isset($this->filters['offset'])) {
			$query->offset(max(0, (int) $filters['offset']));
		}

		// Ordering -- it is first, so it overrides other filters
		if (isset($filters['order-by']) && !isset($this->filters['order-by'])) {
			$query->orderBy($query->quoteIdent($filters['order-by'])
				.(isset($filters['order-asc']) && !$filters['order-asc'] ? ' DESC' : ' ASC'));
		}

		// Add filters
		foreach($filters as $property => $value) {
			if (isset($this->filters[$property])) {
				foreach ($this->filters[$property] as $f) {
					$args = array($f['sql']);
					foreach ($f['params'] as $p) {
						$args[] = $filters[$p];
					}	
					call_user_func_array(array($query, $f['stmt']), $args);
				}
			} else {
				$property = str_replace('-', '_', $property);

				if (isset($this->properties[$property])) {
					// Filter name matches property name => is value equal ?
					$query->where($query->quoteIdent($property).' = ?', $value);
				} else {
					// Check if operator is the last character of filter name
					$operator = substr($property, -1);
					if (!preg_match('/^([^><!%~:]+)([><!%~:]+)$/', $property, $m)) {
						continue;
					}
					list(, $property, $operator) = $m;
					if (!isset($this->properties[$property])) {
						continue;
					}

					// Do not forget there is '=' after the operator in URL.
					$p = $query->quoteIdent($property);
					switch ($operator) {
						case '>':
							$query->where("$p >= ?", $value);
							break;
						case '>>':
							$query->where("$p > ?", $value);
							break;
						case '<':
							$query->where("$p <= ?", $value);
							break;
						case '<<':
							$query->where("$p < ?", $value);
							break;
						case '!':
							$query->where("$p != ?", $value);
							break;
						case ':':
							if (is_array($value)) {
								if (isset($value['min']) && isset($value['max'])) {
									$query->where("? <= $p AND $p < ?", $value['min'], $value['max']);
								} else {
									$query->where("? <= $p AND $p < ?", $value[0], $value[1]);
								}
							} else if (preg_match('/^(.+?)(\.\.\.*)(.+)$/', $value, $m)) {
								list(, $min, $op, $max) = $m;
								if ($op == '..') {
									$query->where("? <= $p AND $p < ?", $min, $max);
								} else {
									$query->where("$p BETWEEN ? AND ?", $min, $max);
								}
							}
							break;
						case '!:':
							if (is_array($value)) {
								if (isset($value['min']) && isset($value['max'])) {
									$query->where("$p < ? OR ? <= $p", $value['min'], $value['max']);
								} else {
									$query->where("$p < ? OR ? <= $p", $value[0], $value[1]);
								}
							} else if (preg_match('/^(.+?)(\.\.\.*)(.+)$/', $value, $m)) {
								list(, $min, $op, $max) = $m;
								if ($op == '..') {
									$query->where("$p < ? OR ? <= $p", $min, $max);
								} else {
									$query->where("$p NOT BETWEEN ? AND ?", $min, $max);
								}
							}
							break;
						case '~':
							$query->where($query->quoteIdent($property).' REGEXP ?', $value);
							break;
						case '!~':
							$query->where($query->quoteIdent($property).' NOT REGEXP ?', $value);
							break;
						case '%':
							$query->where($query->quoteIdent($property).' LIKE ?', $value);
							break;
						case '!%':
							$query->where($query->quoteIdent($property).' NOT LIKE ?', $value);
							break;
					}
				}
			}
		}

		// Query is ready
		//debug_dump($filters, 'Filters');
		//debug_dump("\n".$query->getSqlQuery(), 'Query');
		//debug_dump($query->getSqlParams(), 'Params');
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

