<?php
/*
 * Copyright (c) 2013-2017, Josef Kufner  <josef@kufner.cz>
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

use Smalldb\Flupdo\IFlupdo;


/**
 * Base class for state machines accessed via Flupdo.
 *
 * Most of its protected member properties can be set via config options
 * injected during initialization.
 *
 * ### Configuration Schema
 *
 * The state machine is configured using JSON object passed to the constructor
 * (the `$config` parameter). The object must match the following JSON schema
 * ([JSON format](FlupdoMachine.schema.json)):
 *
 * @htmlinclude doxygen/html/FlupdoMachine.schema.html
 */
class FlupdoMachine extends AbstractMachine
{
	/**
	 * Database connection.
	 *
	 * @var Smalldb\Flupdo\IFlupdo
	 */
	protected $flupdo;

	/**
	 * Sphinx indexer connection.
	 *
	 * @var Smalldb\Flupdo\IFlupdo
	 */
	protected $sphinx;

	/**
	 * Authenticator (gets user id and role)
	 *
	 * @var Smalldb\StateMachine\Auth\IAuth
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
	 * List of properties, which are composed of multiple columns
	 */
	protected $composed_properties = array();

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
	 * Additional filters data definition for listing
	 */
	protected $additional_filters_data = null;

	/**
	 * Select expression for selecting machine state
	 */
	protected $state_select = null;


	/**
	 * Constructor.
	 */
	public function __construct(IFlupdo $flupdo, IAuth $auth = null, IFlupdo $sphinx = null)
	{
		$this->flupdo = $flupdo;
		$this->auth = $auth;
		$this->sphinx = $sphinx;
	}

	/**
	 * Define state machine used by all instances of this type.
	 */
	protected function configureMachine(array $config)
	{
		// Load simple config options
		$this->loadMachineConfig($config, ['table', 'table_alias', 'state_select']);

		// Properties (unless set before)
		if ($this->properties === null) {
			if (empty($config['properties'])) {
				// Scan database for properties if not specified
				$this->scanTableColumns();
			}
		}

		// Setup machine. Properties may be set before initializing,
		// this will merge config with autodetected properties
		parent::configureMachine($config);

		// Collect primary key from properties
		if ($this->pk_columns === null) {
			$this->pk_columns = array();
			foreach ($this->properties as $property => $p) {
				if (!empty($p['is_pk'])) {
					$this->pk_columns[] = $property;
				}
			}
		}

		// Check for primary key
		if (empty($this->pk_columns)) {
			throw new InvalidArgumentException('Primary key is missing in table '.var_export($this->table, true));
		}

		// Prepare list of composed properties and encoded columns
		$this->composed_properties = array();
		$this->json_columns = array();
		foreach ($this->properties as $property => $p) {
			if (!empty($p['components'])) {
				$this->composed_properties[$property] = $p['components'];
			}
			if (isset($p['column_encoding']) && $p['column_encoding'] == 'json') {
				$this->json_columns[] = $property;
			}
		}
	}


	/**
	 * Scan table in database and populate properties.
	 */
	protected function scanTableColumns()
	{
		$driver = $this->flupdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

		switch ($driver) {
			case 'sqlite':
				$r = $this->flupdo->query('PRAGMA table_info('.$this->flupdo->quoteIdent($this->table).')');

				// build properties description
				$this->properties = array();
				foreach($r as $cm) {
					$this->properties[$cm['name']] = array(
						'name' => $cm['name'],
						'type' => $cm['type'],
						'is_pk' => (bool) $cm['pk'],
					);
				}
				break;
			default:
				$r = $this->flupdo->select('*')
					->from($this->flupdo->quoteIdent($this->table))
					->where('NULL')->limit(0)
					->query();
				$col_cnt = $r->columnCount();

				// build properties description
				$this->properties = array();
				for ($i = 0; $i < $col_cnt; $i++) {
					$cm = $r->getColumnMeta($i);
					$this->properties[$cm['name']] = array(
						'name' => $cm['name'],
						'type' => $cm['native_type'],
						'is_pk' => in_array('primary_key', $cm['flags']),
					);
				}
				break;
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

		if ($this->auth->isAllMighty()) {
			return true;
		}

		switch ($access_policy['type']) {

			// anyone: Completely open for anyone
			case 'anyone':
				return true;

			// nobody: Nobody is allowed, except all mighty users.
			case 'nobody':
				return false;

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

		if ($this->auth->isAllMighty()) {
			// FIXME: Remove hardcoded role name
			return;
		}

		switch ($access_policy['type']) {

			// anyone: Completely open for anyone
			case 'anyone':
				return;

			// anonymous: Only anonymous users allowed (not logged in)
			case 'anonymous':
				$user_id = $this->auth->getUserId();
				$query->where($user_id === null ? 'TRUE' : 'FALSE');
				return;

			// user: All logged-in users allowed
			case 'user':
				$user_id = $this->auth->getUserId();
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
	 * Invoke state machine transition. State machine is not instance of
	 * this class, but it is represented by record in database.
	 *
	 * If transition creates a transaction and throws an exception, the
	 * transaction will be rolled back automatically before re-throwing
	 * the exception.
	 */
	public function invokeTransition(Reference $ref, $transition_name, $args, & $returns, callable $new_id_callback = null)
	{
		$transaction_before_transition = $this->flupdo->inTransaction();
		try {
			return parent::invokeTransition($ref, $transition_name, $args, $returns, $new_id_callback);
		}
		catch (\Exception $ex) {
			if (!$transaction_before_transition && $this->flupdo->inTransaction()) {
				$this->flupdo->rollback();
			}
			throw $ex;
		}
	}


	/**
	 * Create generic listing on this machine type.
	 *
	 * @see FlupdoGenericListing
	 */
	public function createListing($filters, $filtering_flags = 0)
	{
		$q = $this->createQueryBuilder();
		$this->queryAddStateSelect($q);
		$this->queryAddPropertiesSelect($q);

		if (isset($this->listing_access_policy)) {
			$this->queryAddAccessPolicyCondition($this->listing_access_policy, $q);
		}

		if (isset($this->default_filters)) {
			$filters = array_replace($this->default_filters, $filters);
		}

		return new \Smalldb\StateMachine\FlupdoGenericListing($this, $q, $this->sphinx, $filters,
			$this->table, $this->filters, $this->properties, $this->references, $this->additional_filters_data, $this->state_select, $filtering_flags);
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
		$query->from($this->queryGetThisTable($query));
	}


	/**
	 * Get table name with alias
	 *
	 * @param $query is used for quoting. It can be Flupdo or FlupdoBuilder
	 * @return quoted SQL fragment containing table name, i.e. "`table` AS `this`".
	 */
	protected function queryGetThisTable($query)
	{
		if ($this->table_alias) {
			return $query->quoteIdent($this->table).' AS '.$query->quoteIdent($this->table_alias);
		} else {
			return $query->quoteIdent($this->table);
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
	 * Add a single property to the query.
	 */
	private function queryAddPropertySelect(\Smalldb\Flupdo\FlupdoBuilder $query, string $table_alias, string $property_name, array $property, string $property_prefix = '')
	{
		$table_q = $query->quoteIdent($table_alias);
		$p_q = $query->quoteIdent($property_name);
		$pa_q = $query->quoteIdent($property_prefix.$property_name);
		if (!empty($property['components'])) {
			foreach ($property['components'] as $component => $column) {
				if (!isset($this->properties[$column])) {
					// make sure the components are selected, but only if they are not standalone properties
					$column_q = $query->quoteIdent($column);
					$alias_q = $property_prefix !== '' ? $query->quoteIdent($property_prefix.$column) : $column_q;
					$query->select("$table_q.$column_q AS $alias_q");
				}
			}
		} else if ((!empty($property['is_calculated']) || !empty($property['calculated'])) && isset($property['sql_select'])) {
			// FIXME: Provide a symbol for the current table alias - this will not work when importing properties.
			$query->select("({$property['sql_select']}) AS $pa_q");
		} else {
			$query->select("$table_q.$p_q AS $pa_q");
		}
	}

	/**
	 * Add properties to select.
	 *
	 * TODO: Skip some properties in listings.
	 */
	protected function queryAddPropertiesSelect(\Smalldb\Flupdo\FlupdoBuilder $query)
	{
		$table = $this->table_alias ? $this->table_alias : $this->table;
		$table_q = $query->quoteIdent($this->table_alias ? $this->table_alias : $this->table);

		// Add properties (some may be calculated)
		foreach ($this->properties as $pi => $p) {
			if ($pi == 'state') {
				// State is not accepted as property and 'state' is reserved name.
				continue;
			}
			$this->queryAddPropertySelect($query, $table, $pi, $p);
		}

		// Import foreign properties using references
		if (!empty($this->references)) {
			foreach ($this->references as $r => $ref) {
				$ref_machine = $this->smalldb->getMachine($ref['machine_type']);
				if (!$ref_machine) {
					throw new \InvalidArgumentException(sprintf('Unknown machine type "%s" for reference "%s".', $ref['machine_type'], $r));
				}

				// Generate reference join
				$ref_alias = 'ref_'.$r;
				$ref_alias_q = $query->quoteIdent($ref_alias);
				$ref_table = $ref_machine->table;
				$ref_table_q = $query->quoteIdent($ref_table);

				$ref_that_id = $ref_machine->describeId();
				$ref_this_id = $ref['machine_id'];

				$id_len = count($ref_that_id);
				if ($id_len != count($ref_this_id)) {
					throw new \InvalidArgumentException('Reference ID has incorrect length ('.$r.').');
				}

				if (isset($ref['query'])) {
					// TODO: Refactor query building syntax to be used everywhere; see $filters in FlupdoGenericListing
					foreach ($ref['query'] as $f) {
						$args = array($f['sql']);
						if (isset($f['params'])) {
							foreach ($f['params'] as $p) {
								$args[] = $query_filters[$p];
							}
						}
						call_user_func_array(array($this->query, $f['stmt']), $args);
					}
				} else {
					// Join refered table
					$on = array();
					for ($i = 0; $i < $id_len; $i++) {
						$on[] = "$table_q.${ref_this_id[$i]} = $ref_alias_q.${ref_that_id[$i]}";
					}
					$query->leftJoin("$ref_table_q AS $ref_alias_q ON ".join(' AND ', $on));
				}

				// Import referred properties
				foreach ($ref_machine->properties as $p => $ref_p) {
					if (!empty($ref_p['is_referred'])) {
						$this->queryAddPropertySelect($query, $ref_alias, $p, $ref_p, $r.'_');
					}
				}

				// Import explicitly referred properties
				if (!empty($ref['properties'])) {
					foreach ($ref['properties'] as $p => $ref_p) {
						$this->queryAddPropertySelect($query, $ref_alias, $p, $ref_p, $r.'_');
					}
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
		if ($this->auth->isAllMighty()) {
			$query->$clause('TRUE'.$policy_alias);
		} else {
			$query->$clause('('.$policy['sql_select'].')'.$policy_alias);
		}
	}


	/**
	 * Add user-relation policy condition to SQL query (select or where clause).
	 */
	private function queryAddUserRelationAccessPolicyCondition($policy_name, $policy, $query, $clause)
	{
		$policy_alias = $clause == 'select' ? ' AS '.$query->quoteIdent('_access_policy_'.$policy_name) : '';
		$user_id = $this->auth->getUserId();
		if ($this->auth->isAllMighty()) {
			$query->$clause('TRUE'.$policy_alias);
		} else if (isset($policy['required_value'])) {
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
			$query->$clause($table.'.'.$query->quoteIdent($col).' = ?', $val);
		}
		return $query;
	}


	/**
	 * Get current state of state machine.
	 */
	public function getState($id)
	{
		if ($id === null || $id === array() || $id === false || $id === '') {
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
			throw new InstanceDoesNotExistException('State machine instance does not exist (null ID).');
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
			throw new InstanceDoesNotExistException('State machine instance not found: '
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
				if (empty($prop['required']) && ($v === array() || (is_string($v) && ctype_space($v)))) {
					$v = null;
				}
			} else {
				// Value is not valid property, throw it away
				unset($properties[$k]);
			}
		}

		// Split composed properties to individual columns
		foreach ($this->composed_properties as $property => $components) {
			foreach ($components as $component => $column) {
				if (isset($properties[$property][$component])) {
					$properties[$column] = $properties[$property][$component];
				} else {
					$properties[$column] = null;
				}
			}
			unset($properties[$property]);
		}

		// Encode JSON columns
		foreach ($this->json_columns as $column_name) {
			if (isset($properties[$column_name])) {
				$json_data = json_encode($properties[$column_name], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
				if ($json_data === FALSE) {
					switch (json_last_error()) {
						case JSON_ERROR_NONE: $json_error = 'No error has occurred'; break;
						case JSON_ERROR_DEPTH: $json_error = 'The maximum stack depth has been exceeded'; break;
						case JSON_ERROR_STATE_MISMATCH: $json_error = 'Invalid or malformed JSON'; break;
						case JSON_ERROR_CTRL_CHAR: $json_error = 'Control character error, possibly incorrectly encoded'; break;
						case JSON_ERROR_SYNTAX: $json_error = 'Syntax error'; break;
						case JSON_ERROR_UTF8: $json_error = 'Malformed UTF-8 characters, possibly incorrectly encoded'; break;
						case JSON_ERROR_RECURSION: $json_error = 'One or more recursive references in the value to be encoded'; break;
						case JSON_ERROR_INF_OR_NAN: $json_error = 'One or more NAN or INF values in the value to be encoded'; break;
						case JSON_ERROR_UNSUPPORTED_TYPE: $json_error = 'A value of a type that cannot be encoded was given'; break;
						default: $json_error = 'Error '.json_last_error(); break;
					}
					throw new \InvalidArgumentException('Failed to serialize invoice data to JSON: '.$json_error);
				}
				$properties[$column_name] = $json_data;
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
		// Compose components to objects
		foreach ($this->composed_properties as $property => $components) {
			$value = array();
			foreach ($components as $component => $column) {
				$value[$component] = isset($properties[$column]) ? $properties[$column] : null;
			}
			$properties[$property] = $value;
		}

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
		return $this->pk_columns;
	}

}

