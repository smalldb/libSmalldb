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
 *
 * Most of its protected member properties can be set via config options
 * injected during initialization.
 */
abstract class FlupdoMachine extends AbstractMachine
{
	/**
	 * Database connection.
	 */
	protected $flupdo;

	/**
	 * Authenticator (gets user id and role)
	 */
	protected $auth;

	/**
	 * Name of SQL table, where machine properties are stored.
	 */
	protected $table;

	/**
	 * Alias of the $table. Default is to not use it.
	 */
	protected $table_alias = null;

	/**
	 * List of columns which are used as primary key.
	 */
	protected $pk_columns = null;

	/**
	 * List of columns which are serialized as JSON in database.
	 */
	protected $json_columns = array();

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
	 * Filters defined in configuration
	 */
	protected $filters = null;

	/**
	 * Default filters for listing
	 */
	protected $default_filters = null;

	/**
	 * Select expression for selecting machine state
	 */
	protected $state_select = null;

	/**
	 * Define state machine used by all instances of this type.
	 */
	protected function initializeMachine($config)
	{
		// Get flupdo resource
		$flupdo_resource_name = isset($config['flupdo_resource']) ? $config['flupdo_resource'] : 'database';
		$this->flupdo = is_array($this->context) ? $this->context[$flupdo_resource_name] : $this->context->$flupdo_resource_name;
		if (!($this->flupdo instanceof \Flupdo\Flupdo\Flupdo)) {
			throw new InvalidArgumentException('Flupdo resource is not an instance of \\Smalldb\\Flupdo\\Flupdo.');
		}

		// Get authenticator
		$auth_resource_name = isset($config['auth_resource']) ? $config['auth_resource'] : 'auth';
		$this->auth = is_array($this->context) ? $this->context[$auth_resource_name] : $this->context->$auth_resource_name;
		if ($this->auth !== null && !($this->auth instanceof IAuth)) {
			throw new InvalidArgumentException('Authenticator resource is not an instance of \\Smalldb\\StateMachine\\IAuth.');
		}

		// Use config if not specified otherwise
		if ($this->table === null && isset($config['table'])) {
			$this->table = (string) $config['table'];
		}
		if ($this->table_alias === null && isset($config['table_alias'])) {
			$this->table_alias = (string) $config['table_alias'];
		}
		if ($this->url_fmt === null && isset($config['url'])) {
			$this->url_fmt = (string) $config['url'];
		}
		if ($this->parent_url_fmt === null && isset($config['parent_url'])) {
			$this->parent_url_fmt = (string) $config['parent_url'];
		}
		if ($this->post_action_url_fmt === null && isset($config['post_action_url'])) {
			$this->post_action_url_fmt = (string) $config['post_action_url'];
		}
		foreach($this as $k => $v) {
			switch ($k) {
				case 'flupdo':
				case 'table':
				case 'table_alias':
				case 'url_fmt':
				case 'parent_url_fmt':
				case 'post_action_url_fmt':
					break;
				default:
					if ($this->$k === null && isset($config[$k])) {
						$this->$k = $config[$k];
					}
					break;
			}
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
				if (isset($p['column_encoding']) && $p['column_encoding'] == 'json') {
					$this->json_columns[] = $property;
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
	 * Returns true if user has required access_policy.
	 *
	 * TODO: Caching ? Reference object has property cache. It would be
	 * 	nice to pass it here.
	 */
	protected function checkAccessPolicy($access_policy_name, Reference $ref)
	{
		// Allow by default
		if (empty($access_policy_name)) {
			return true;
		}

		if (!isset($this->access_policies[$access_policy_name])) {
			throw new \InvalidArgumentException('Unknown policy: '.$access_policy_name);
		}
		$access_policy = $this->access_policies[$access_policy_name];

		//debug_dump($access_policy, 'POLICY: '.$access_policy_name.' @ '.get_class($this));

		if ($this->auth->hasUserRoles(array('admin', 'root'))) {
			// FIXME: Remove hardcoded role name
			return true;
		}

		switch ($access_policy['type']) {

			// anyone: Completely open for anyone
			case 'anyone':
				return true;

			// anonymous: Only anonymous users allowed (not logged in)
			case 'anonymous':
				$user_id = $this->auth->getUserId();
				return $user_id === null;

			// user: All logged-in users allowed
			case 'user':
				$user_id = $this->auth->getUserId();
				return $user_id !== null;

			// owner: Owner must match current user
			case 'owner':
				if ($ref->isNullRef()) {
					// Everyone owns nothing :)
					return true;
				}
				$properties = $ref->properties;
				$user_id = $this->auth->getUserId();
				$owner_property = $access_policy['owner_property'];
				if (isset($access_policy['session_state'])) {
					if ($this->auth->getSessionMachine()->state != $access_policy['session_state']) {
						return false;
					}
				}
				return $user_id !== null ? $user_id == $properties[$owner_property] : $properties[$owner_property] === null;

			// role: Current user must have specified role ($ref is ignored)
			case 'role':
				return $this->auth->hasUserRoles($access_policy['required_role']);

			// These are done by SQL select.
			case 'condition':
				if ($ref->isNullRef()) {
					// Nonexistent entity has nulls everywhere
					// FIXME: Are we sure?
					return false;
				}
				$properties = $ref->properties;
				return !empty($properties['_access_policy_'.$access_policy_name]);

			// These are done by SQL select.
			case 'user_relation':
				if ($ref->isNullRef()) {
					// No relation to nonexistent entity.
					return false;
				}
				$properties = $ref->properties;
				return !empty($properties['_access_policy_'.$access_policy_name]);

			// unknown policies are considered unsafe
			default:
				return false;
		}

		// This should not happen.
		throw new \RuntimeException('Policy '.$policy.' did not decide.');
	}


	/**
	 * Adds conditions to enforce read access_policy to query object.
	 */
	protected function queryAddAccessPolicyCondition($access_policy_name, $query)
	{
		// Allow by default
		if (empty($access_policy_name)) {
			return;
		}

		if (!isset($this->access_policies[$access_policy_name])) {
			throw new \InvalidArgumentException('Unknown policy: '.$access_policy_name);
		}
		$access_policy = $this->access_policies[$access_policy_name];

		//debug_dump($access_policy, 'POLICY: '.$access_policy_name.' @ '.get_class($this));

		if ($this->auth->hasUserRoles('admin')) {
			// FIXME: Remove hardcoded role name
			return;
		}

		switch ($access_policy['type']) {

			// anyone: Completely open for anyone
			case 'anyone':
				return;

			// anonymous: Only anonymous users allowed (not logged in)
			case 'anonymous':
				$query->where($user_id === null ? 'TRUE' : 'FALSE');
				return;

			// user: All logged-in users allowed
			case 'user':
				$query->where($user_id !== null ? 'TRUE' : 'FALSE');
				return;

			// owner: Owner must match current user
			case 'owner':
				$user_id = $this->auth->getUserId();
				$owner_property = $query->quoteIdent($access_policy['owner_property']);
				$table = $query->quoteIdent($this->table);
				if (isset($access_policy['session_state'])) {
					if ($this->auth->getSessionMachine()->state != $access_policy['session_state']) {
						$query->where('FALSE');
						return;
					}
				}
				if ($user_id === null) {
					$query->where("$table.$owner_property IS NULL");
					return;
				} else {
					$query->where("$table.$owner_property = ?", $user_id);
					return;
				}

			// role: Current user must have specified role ($ref is ignored)
			case 'role':
				$query->where($this->auth->hasUserRoles($access_policy['required_role']) ? 'TRUE' : 'FALSE');
				return;

			// These are done by SQL select.
			case 'condition':
				$this->queryAddSimpleAccessPolicyCondition($access_policy_name, $access_policy, $query, 'where');
				return;

			// These are done by SQL select.
			case 'user_relation':
				$this->queryAddUserRelationAccessPolicyCondition($access_policy_name, $access_policy, $query, 'where');
				return;

			// unknown policies are considered unsafe
			default:
				$query->where('FALSE');
				return false;
		}

		// This should not happen.
		throw new \RuntimeException('Policy '.$policy.' did not decide.');
	}


	/**
	 * Create generic listing on this machine type.
	 *
	 * @see FlupdoGenericListing
	 */
	public function createListing($filters)
	{
		$q = $this->createQueryBuilder();
		$this->queryAddStateSelect($q);
		$this->queryAddPropertiesSelect($q);

		if (isset($this->default_filters)) {
			$filters = array_replace($this->default_filters, $filters);
		}

		return new \Smalldb\StateMachine\FlupdoGenericListing($this, $q, $filters,
			$this->table, $this->filters, $this->properties, $this->references);
	}


	/**
	 * Create query builder.
	 */
	public function createQueryBuilder()
	{
		$q = $this->flupdo->select();
		$this->queryAddFrom($q);
		$this->queryAddAccessPolicyCondition($this->read_access_policy, $q);
		return $q;
	}


	/**
	 * Add FROM clause
	 */
	protected function queryAddFrom($query)
	{
		if ($this->table_alias) {
			$query->from($query->quoteIdent($this->table).' AS '.$query->quoteIdent($this->table_alias));
		} else {
			$query->from($query->quoteIdent($this->table));
		}
	}


	/**
	 * Add state column into select clause of the $query.
	 *
	 * Must add only one column.
	 */
	protected function queryAddStateSelect($query)
	{
		if (isset($this->state_select)) {
			$query->select("({$this->state_select}) AS `state`");
		} else {
			throw new \RuntimeException('State select not defined and '.__METHOD__.' not overriden.');
		}
	}


	/**
	 * Add properties to select.
	 *
	 * TODO: Skip some properties in lisings.
	 */
	protected function queryAddPropertiesSelect($query)
	{
		$table = $query->quoteIdent($this->table_alias ? $this->table_alias : $this->table);

		// Add properties (some may be calculated)
		foreach ($this->properties as $pi => $p) {
			$pi_quoted = $query->quoteIdent($pi);
			if (!empty($p['calculated']) && isset($p['sql_select'])) {
				$query->select("({$p['sql_select']}) AS $pi_quoted");
			} else {
				$query->select("$table.$pi_quoted AS $pi_quoted");
			}
		}

		// Import foreign properties using references
		if (!empty($this->references)) {
			foreach ($this->references as $r => $ref) {
				$ref_machine = $this->backend->getMachine($ref['machine_type']);
				if (!$ref_machine) {
					throw new \InvalidArgumentException(sprintf('Unknown machine type "%s" for reference "%s".', $ref['machine_type'], $r));
				}

				$ref_alias = $query->quoteIdent('ref_'.$r);
				$ref_table = $query->quoteIdent($ref_machine->table);

				$ref_that_id = $ref_machine->describeId();
				$ref_this_id = $ref['machine_id'];

				$id_len = count($ref_that_id);
				if ($id_len != count($ref_this_id)) {
					throw new \InvalidArgumentException('Reference ID has incorrect length ('.$r.').');
				}

				// Join refered table
				$on = array();
				for ($i = 0; $i < $id_len; $i++) {
					$on[] = "$table.${ref_this_id[$i]} = $ref_alias.${ref_that_id[$i]}";
				}
				$query->leftJoin("$ref_table AS $ref_alias ON ".join(' AND ', $on));

				// Import properties
				foreach ($ref['properties'] as $p => $ref_p) {
					$query->select($ref_alias.'.'.$query->quoteIdent($ref_p).' AS '.$query->quoteIdent($p));
				}
			}
		}

		// Add access policy columns
		if (!empty($this->access_policies)) {
			//debug_dump($this->access_policies);
			foreach ($this->access_policies as $policy_name => $policy) {
				switch ($policy['type']) {
					case 'condition':
						$this->queryAddSimpleAccessPolicyCondition($policy_name, $policy, $query, 'select');
						break;
					case 'user_relation':
						$this->queryAddUserRelationAccessPolicyCondition($policy_name, $policy, $query, 'select');
						break;
				}
			}
		}

		//debug_dump("\n".$query->getSqlQuery(), 'Query', true);
	}


	/**
	 * Add simple policy condition to SQL query (select or where clause).
	 */
	private function queryAddSimpleAccessPolicyCondition($policy_name, $policy, $query, $clause)
	{
		$policy_alias = $clause == 'select' ? ' AS '.$query->quoteIdent('_access_policy_'.$policy_name) : '';
			$query->$clause('('.$policy['sql_select'].')'.$policy_alias);
	}


	/**
	 * Add user-relation policy condition to SQL query (select or where clause).
	 */
	private function queryAddUserRelationAccessPolicyCondition($policy_name, $policy, $query, $clause)
	{
		$policy_alias = $clause == 'select' ? ' AS '.$query->quoteIdent('_access_policy_'.$policy_name) : '';
		$user_id = $this->auth->getUserId();
		if (isset($policy['required_value'])) {
			$query->$clause('('.$policy['sql_select'].') = ?'.$policy_alias, $user_id, $policy['required_value']);
		} else {
			$query->$clause('('.$policy['sql_select'].') IS NOT NULL'.$policy_alias, $user_id);
		}
	}


	/**
	 * Add primary key condition to where clause. Result should contain
	 * only one row now.
	 *
	 * Returns $query.
	 */
	protected function queryAddPrimaryKeyWhere($query, $id, $clause = 'where')
	{
		$table = $query->quoteIdent($this->table_alias ? $this->table_alias : $this->table);

		if ($id === null || $id === array() || $id === false || $id === '') {
			throw new InvalidArgumentException('Empty ID.');
		} else if (count($id) != count($this->describeId())) {
			throw new InvalidArgumentException(sprintf('Malformed ID: got %d pieces of %d.', count($id), count($this->describeId())));
		}
		foreach (array_combine($this->describeId(), (array) $id) as $col => $val) {
			$query->$clause($query->quoteIdent($table).'.'.$query->quoteIdent($col).' = ?', $val);
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
			throw new RuntimeException('State machine instance does not exist (null ID).');
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

		if ($props === false) {
			throw new RuntimeException('State machine instance not found: '
				.json_encode($id, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE| JSON_NUMERIC_CHECK));
		}

		if ($this->load_state_with_properties) {
			$state_cache = array_pop($props);
		}

		return $this->decodeProperties($props);
	}


	/**
	 * Encode properties to database representation.
	 *
	 * NULL values are preserved.
	 *
	 * You should not need to call this, it is called automaticaly at the right time.
	 */
	public function encodeProperties($properties)
	{
		// Replace empty values with null, if value is not required
		foreach ($properties as $k => & $v) {
			if (isset($this->properties[$k])) {
				$prop = $this->properties[$k];
				if (empty($prop['required']) && ($v === array() || ctype_space($v))) {
					$v = null;
				}
			} else {
				// Value is not valid property, throw it away
				unset($properties[$k]);
			}
		}

		// Encode JSON columns
		foreach ($this->json_columns as $column_name) {
			if (isset($properties[$column_name])) {
				$properties[$column_name] = json_encode($properties[$column_name],
					JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
			}
		}

		return $properties;
	}


	/**
	 * Decode properties from database representation
	 *
	 * NULL values are preserved.
	 *
	 * You should not need to call this, it is called automaticaly at the right time.
	 */
	public function decodeProperties($properties)
	{
		// Decode JSON columns
		foreach ($this->json_columns as $column_name) {
			if (isset($properties[$column_name])) {
				$properties[$column_name] = json_decode($properties[$column_name], TRUE, 512, JSON_BIGINT_AS_STRING);
			}
		}
		return $properties;
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

