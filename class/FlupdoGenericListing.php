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
 * A very generic listing based on Flupdo::SelectBuilder
 *
 *
 * Default Filter Syntax
 * ---------------------
 *
 * Syntax is designed for use in query part of URL. First, filter name
 * is looked up in statemachine filters. If not found, filter name is
 * considered a property name and if matches, equality condition is
 * added. Otherwise if operator is detected at the end of property
 * name, given condition is added.
 *
 * @note Please keep in mind that there is '=' character in URL between
 * 	filter name and value. For example '<' filter looks like
 * 	`...?property<=value`.
 *
 * @warning Filters are in fact simple key-value structure. Conditions
 * 	described below are only an ilusion. All this only means that for each
 * 	state machine property a group of filters is generated (on demand).
 *
 * ### Conditions:
 *
 *   - `!` means "not equal".
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
 *
 * ### Predefined filters:
 *
 *   - `limit` (int; default = 100)
 *   - `offset` (int; default = undefined)
 *   - `order_by` (string; comma separated list of properties/column names;
 *     property prefixed with minus is sorted in oposite order;
 *     default = undefined) - `order_asc` (bool; default = true; applied only
 *     when order_by is set)
 *   - `order-by` (alias to `order_by`)
 *   - `order-asc` (alias to `order_asc`, but only if `order-by` is used)
 *
 * ### Example:
 *
 * Select all items, where `foo` is greater or equal to 5, and `bar` is between
 * 10 and 20 (excluding 20), and `category` is 'fruit'.
 *
 *     http://example.com/items?foo>=5&bar:=10..20&category=fruit
 *
 *     $filter = array(
 *         'foo>' => 5,
 *         'bar:' => array(10, 20),
 *         'category' => 'fruit',
 *     )
 *
 *
 * Custom filters
 * --------------
 *
 * FlupdoMachine machine can have filters option set. This option defines
 * custom filters. Custom filters will override predefined filters of the same
 * name.
 *
 * Each filter is list of statements added to Flupdo\SelectBuilder. Each filter
 * must have these properties defined:
 *
 *   - `stmt`: Name of SelectBuilder method which adds the statement.
 *   - `sql`: Raw SQL code with positional parameters (question marks).
 *   - `params`: Filter names wich values will be passed as query parameters.
 *     These must be in the same order as in `sql`.
 *
 * @note Operators of default filters have nothing to do with custom filters.
 *
 * Filter can be defined as a simple filter using `query` property, or as
 * value-dependent filter using `query_map` property. The `query_map` will
 * select one of specified filters by filter value. The `query` is used when
 * `query_map` is missing or no value is matched.
 *
 * ### Example
 *    
 *     "filters": {
 *         "path": {
 *             "query": [
 *                 {
 *                     "stmt": "where",
 *                     "sql": "`path_mask` = ? OR `path_mask` = \"*\" OR ? REGEXP CONCAT(\"^\", REPLACE(`path_mask`, \"*\", \"[^/]+\"), \"$\")",
 *                     "params": [ "path", "path" ]
 *                 }
 *             ]
 *         },
 *         "date": {
 *             "query_map": {
 *                 "past": [
 *                     {
 *                         "stmt": "where",
 *                         "sql": "mtime < NOW()",
 *                         "params": [ ]
 *                     }
 *                 ],
 *                 "future": [
 *                     {
 *                         "stmt": "where",
 *                         "sql": "mtime >= NOW()",
 *                         "params": [ ]
 *                     }
 *                 ]
 *             },
 *             "query": [
 *                {
 *                    "stmt": "where",
 *                    "sql": "mtime BETWEEN NOW() - INTEVAL ? DAY AND NOW + INTERVAL ? DAY",
 *                    "params": [ "date", "date" ]
 *                }
 *             ]
 *         }
 *     }
 *
 * Possible use:
 *
 *   - `http://example.com/item?date=past&path=/foo/bar` -- select items
 *     modified in past and matching given path.
 *   - `http://example.com/item?date=2` -- select items modified within
 *     +/- 2 days from now.
 *
 * @note Additional properties may be added to filter definition. It is
 * 	expected that GUI will be generated from this definition as well.
 *
 * @warning Do not use GROUP BY, it will break item count calculation. Use
 * 	sub-selects instead.
 */
class FlupdoGenericListing implements IListing
{

	protected $machine;			///< Parent state machine, which created this listing.
	protected $query;			///< SQL query to execute.
	protected $result;			///< PDOStatement, result of the query.
	protected $query_filters;		///< Actual filters.
	protected $additional_filters_data;	///< Additional filter data source definition (these are evaluated and added to processed filters).
	protected $state_select;		///< SQL expression to select machine state

	private   $before_called = false;	///< True, when before_query was called.
	protected $before_query = array();	///< List of callables to be called just before the query is executed.
	protected $after_query = array();	///< List of callables to be called in destructor, only if the query has been executed.
	protected $unknown_filters = array();	///< Unknown filters - subset of $query_filters.


	/**
	 * Prepare query builder
	 *
	 * @param $machine State machine implementation to inspect.
	 * @param $query_builder Query builder where filters will be added.
	 * @param $sphinx Flupdo instance connected to Sphinx index (optional)
	 * @param $query_filters Requested filters to add to $query_builder.
	 * @param $machine_filters Custom filter definitions (how things should be filtered, not filtering itself).
	 * @param $machine_properties State machine properties definitions.
	 * @param $machine_references State machine references to other state machines.
	 */
	public function __construct(
		AbstractMachine $machine,
		\Flupdo\Flupdo\SelectBuilder $query_builder,
		\Flupdo\Flupdo\IFlupdo $sphinx = null,
		$query_filters,
		$machine_table, $machine_filters, $machine_properties, $machine_references,
		$additional_filters_data, $state_select, $filtering_flags = 0)
	{
		$this->machine = $machine;
		$this->query_filters = $query_filters;
		$this->additional_filters_data = $additional_filters_data;
		$this->state_select = $state_select;

		$ignore_unknown_filters = ($filtering_flags & self::IGNORE_UNKNOWN_FILTERS);

		// Prepare query builder
		$this->query = $query_builder;
		$this->sphinx = $sphinx;
		$machine_table = $this->query->quoteIdent($machine_table);

		// Limit & offset
		if (isset($query_filters['limit']) && !isset($machine_filters['limit'])) {
			if ($query_filters['limit'] !== false) {	// false == infinity
				$this->query->limit((int) $query_filters['limit']);
			}
		} else {
			// Fail-safe
			$this->query->limit(150);
		}
		if (isset($query_filters['offset']) && !isset($machine_filters['offset'])) {
			$this->query->offset(max(0, (int) $query_filters['offset']));
		}

		// Ordering -- it is first, so it overrides other filters
		if (isset($query_filters['order_by']) && !isset($machine_filters['order_by'])) {
			$order_by = $query_filters['order_by'];
			$order_asc = (isset($query_filters['order_asc']) ? (bool) $query_filters['order_asc'] : true);
		}
		else if (isset($query_filters['order-by']) && !isset($machine_filters['order-by'])) {
			$order_by = $query_filters['order-by'];
			$order_asc = (isset($query_filters['order-asc']) ? (bool) $query_filters['order-asc'] : true);
		} else {
			$order_by = null;
			$order_asc = null;
		}

		if ($order_by !== null) {
			foreach (preg_split("/\s*,\s*/", $order_by) as $o) {
				if ($o[0] == '-') {
					$this->query->orderBy($this->query->quoteIdent(substr($o, 1)) . ($order_asc ? ' DESC' : ' ASC'));
				} else {
					$this->query->orderBy($this->query->quoteIdent($o). ($order_asc ? ' ASC' : ' DESC'));
				}
			}
		}

		// Add filters
		foreach($query_filters as $filter_name => $value) {
			if ($value === null || $value === '') {
				unset($this->query_filters[$filter_name]);	// Drop not applied filters
				continue;
			}
			$filter_name = str_replace('-', '_', $filter_name);
			if ($filter_name == 'limit' || $filter_name == 'offset' || $filter_name == 'order_by' || $filter_name == 'order_asc') {
				// Filters handled above
				continue;
			} else if (isset($machine_filters[$filter_name])) {
				// Custom filter
				if (isset($machine_filters[$filter_name]['query_map'][$value])) {
					// Check query_map for the value
					$filters = $machine_filters[$filter_name]['query_map'][$value];
				} else if (isset($machine_filters[$filter_name]['query'])) {
					// Fallback if query_map does not contain value or does not exist at all
					$filters = $machine_filters[$filter_name]['query'];
				} else if (isset($machine_filters[$filter_name]['sphinx_fulltext'])) {
					// Sphinx connector
					$this->setupSphinxSearch($filter_name, $value, $machine_filters[$filter_name]);
					continue;
				} else {
					// No filter here.
					continue;
				}

				// Add filter to query builder
				foreach ($filters as $f) {
					$args = array($f['sql']);
					if (isset($f['params'])) {
						foreach ($f['params'] as $p) {
							$args[] = $query_filters[$p];
						}
					}
					call_user_func_array(array($this->query, $f['stmt']), $args);
				}
			} else if ($filter_name == 'state') {
				$this->query->where('('.$this->state_select.') = ?', $value);
			} else if ($filter_name == 'state!') {
				$this->query->where('('.$this->state_select.') != ?', $value);
			} else {
				// Use default property filter
				$property = str_replace('-', '_', $filter_name);

				if (isset($machine_properties[$property])) {
					// Filter name matches property name => is value equal ?
					$this->query->where($machine_table.'.'.$this->query->quoteIdent($property).' = ?', $value);
				} else if (isset($machine_references[$property])) {
					// Filter by reference
					$id_properties = $machine_references[$property]['machine_id'];
					$id_parts = count($id_properties);

					// Detect ID
					if (is_object($value)) {
						if (!($value instanceof Reference)) {
							throw new \InvalidArgumentException('Filter value is not an instance of Reference.');
						} else if ($value->machine_type != $machine_references[$property]['machine_type']) {
							throw new \InvalidArgumentException('Referenced machine type does not match reference machine type.');
						} else {
							$id_values = (array) $value->id;
						}
					} else if ($value === null) {
						$id_values = null;
					} else if (count($id_parts) == 1) {
						$id_values = (array) $value;
					} else {
						throw new \InvalidArgumentException('Cannot filter by composed reference using single value.');
					}

					// Add where clause for each ID fragment
					if ($id_values === null) {
						// Null ref
						for ($i = 0; $i < $id_parts; $i++) {
							$this->query->where($machine_table.'.'.$this->query->quoteIdent($id_properties[$i]).' IS NULL');
						}
					} else {
						// Non-null ref
						for ($i = 0; $i < $id_parts; $i++) {
							$this->query->where($machine_table.'.'.$this->query->quoteIdent($id_properties[$i]).' = ?', $id_values[$i]);
						}
					}
				} else {
					// Check if operator is the last character of filter name
					$operator = substr($property, -1);
					if (!preg_match('/^([^><!%~:?]+)([><!%~:?]+)$/', $property, $m)) {
						goto unknown_filter;
					}
					list(, $property, $operator) = $m;
					if (!isset($machine_properties[$property])) {
						goto unknown_filter;
					}

					// Do not forget there is '=' after the operator in URL.
					$p = $machine_table.'.'.$this->query->quoteIdent($property);
					switch ($operator) {
						case '>':
							$this->query->where("$p >= ?", $value);
							break;
						case '>>':
							$this->query->where("$p > ?", $value);
							break;
						case '<':
							$this->query->where("$p <= ?", $value);
							break;
						case '<<':
							$this->query->where("$p < ?", $value);
							break;
						case '!':
							$this->query->where("$p != ?", $value);
							break;
						case '?':
							$this->query->where($value ? "$p IS NOT NULL" : "$p IS NULL");
							break;
						case ':':
							if (is_array($value)) {
								if (!empty($value['min']) && !empty($value['max'])) {
									$this->query->where("? <= $p AND $p < ?", $value['min'], $value['max']);
								} else if (!empty($value['min'])) {
									$this->query->where("? <= $p", $value['min']);
								} else if (!empty($value['max'])) {
									$this->query->where("$p < ?", $value['max']);
								}
							} else if (preg_match('/^(.+?)(\.\.\.*)(.+)$/', $value, $m)) {
								list(, $min, $op, $max) = $m;
								if ($op == '..') {
									$this->query->where("? <= $p AND $p < ?", $min, $max);
								} else {
									$this->query->where("$p BETWEEN ? AND ?", $min, $max);
								}
							}
							break;
						case '!:':
							if (is_array($value)) {
								if (isset($value['min']) && isset($value['max'])) {
									$this->query->where("$p < ? OR ? <= $p", $value['min'], $value['max']);
								} else if (!empty($value['min'])) {
									$this->query->where("$p < ?", $value['min']);
								} else if (!empty($value['max'])) {
									$this->query->where("? <= $p", $value['max']);
								}
							} else if (preg_match('/^(.+?)(\.\.\.*)(.+)$/', $value, $m)) {
								list(, $min, $op, $max) = $m;
								if ($op == '..') {
									$this->query->where("$p < ? OR ? <= $p", $min, $max);
								} else {
									$this->query->where("$p NOT BETWEEN ? AND ?", $min, $max);
								}
							}
							break;
						case '~':
							$this->query->where("$p REGEXP ?", $value);
							break;
						case '!~':
							$this->query->where("$p NOT REGEXP ?", $value);
							break;
						case '%':
							$this->query->where("$p LIKE ?", $value);
							break;
						case '%%':
							$this->query->where("$p LIKE CONCAT('%', ?, '%')", $value);
							break;
						case '!%':
							$this->query->where("$p NOT LIKE ?", $value);
							break;
						case '%%':
							$this->query->where("$p NOT LIKE CONCAT('%', ?, '%')", $value);
							break;
						default:
							goto unknown_filter;
					}
				}
			}
			continue;

			// If filter is not known, we should warn programmer before continuing to a next one.
			unknown_filter: {
				unset($this->query_filters[$filter_name]);
				$this->unknown_filters[$filter_name] = $value;
				continue;
			}
		}

		if (!$ignore_unknown_filters && !empty($this->unknown_filters)) {
			throw new UnknownFiltersException('Unknown filters: ' . join(', ', array_keys($this->unknown_filters)));
		}

		// Query is ready
		//debug_dump($query_filters, 'Filters');
		//debug_dump("\n".$this->query->getSqlQuery(), 'Query');
		//debug_dump($this->query->getSqlParams(), 'Params');
	}


	/**
	 * Get raw query builder. It may be useful to configure query outside 
	 * of this listing, but it is ugly. It is also specific to 
	 * FlupdoGenericListing only.
	 */
	public function getQueryBuilder()
	{
		return $this->query;
	}


	/**
	 * Execute SQL query or do whatever is required to get this listing 
	 * populated.
	 */
	public function query()
	{
		if ($this->result === null) {
			$this->before_called = true;
			foreach ($this->before_query as $callable) {
				$callable();
			}
			try {
				$this->result = $this->query->query();
				$this->result->setFetchMode(\PDO::FETCH_ASSOC);

			}
			catch (\Flupdo\Flupdo\FlupdoSqlException $ex) {
				error_log("Failed SQL query:\n".$this->query->getSqlQuery());
				error_log("Parameters of failed SQL query:\n".$this->query->getSqlParams());
				throw $ex;
			}
		} else {
			throw new RuntimeException('Query already performed.');
		}
		return $this->result;
	}


	public function __destruct()
	{
		if ($this->before_called) {
			foreach ($this->after_query as $callable) {
				$callable();
			}
		}
	}


	/**
	 * Get description of all properties (columns) in the listing.
	 */
	public function describeProperties()
	{
		return array_merge($this->machine->describeAllMachineProperties(), $this->machine->describeAllMachineReferences());
	}


	/**
	 * Returns an array of all items in the listing.
	 *
	 * This decodes properties.
	 */
	public function fetchAll()
	{
		if ($this->result === null) {
			$this->query();
		}
		$machine = $this->machine;

		$id_keys = $this->machine->describeId();

		if (count($id_keys) == 1) {
			$list = array();
			while(($properties = $this->result->fetch(\PDO::FETCH_ASSOC))) {
				$item = $machine->hotRef($machine->decodeProperties($properties));
				$list[$item->id] = $item;
			}
			return $list;
		} else {
			return array_map(function($properties) use ($machine) {
					return $machine->hotRef($machine->decodeProperties($properties));
				}, $this->result->fetchAll(\PDO::FETCH_ASSOC));
		}
	}


	/**
	 * Get filter configuration (processed and filled with pagination
	 * data). This method should be called after query(), otherwise it will
	 * not contain `total_count`.
	 *
	 * The `total_count` is calculated using second query, same as the
	 * primary query, but with `COUNT(*)` in `SELECT` clause, and
	 * limit & offset removed (everything else is preserved). This is a few
	 * orders of magnitude faster than SQL_CALC_FOUND_ROWS.
	 */
	public function getProcessedFilters()
	{
		if ($this->result !== null) {
			$q = clone $this->query;	// make sure we do not modify original query
			$q->uncompile();
			$count = $q->select(null)->orderBy(null)->limit(null)->offset(null)
				->select('COUNT(1)')
				->query()
				->fetchColumn();
			$filters = $this->query_filters;
			$filters['_count'] = $count;
			$this->calculateAdditionalFiltersData($filters);
			return $filters;
		} else {
			return $this->query_filters;
		}
	}


	/**
	 * Return unknown filters, subset of $query_filters.
	 */
	public function getUnknownFilters()
	{
		return $this->unknown_filters;
	}


	/**
	 * Calculate additional filter data.
	 *
	 * @param $filters Filters to populate with additional data.
	 * @return Nothing.
	 */
	protected function calculateAdditionalFiltersData(& $filters)
	{
		if (!empty($this->additional_filters_data)) {
			foreach ($this->additional_filters_data as $f => $src) {
				if (isset($src['query'])) {
					$filters[$f] = $this->query->pdo->query($src['query'])->fetchColumn();
				}
			}
		}
	}


	protected function setupSphinxSearch($filter_name, $value, $machine_filter)
	{
		$sphinx_key_column = $this->query->quoteIdent($machine_filter['sphinx_key_column']);
		$temp_table = $this->query->quoteIdent('_sphinx_temp_'.$filter_name);
		$index_name = $this->query->quoteIdent($machine_filter['index_name']);

		$this->query->where("$sphinx_key_column IN (SELECT $temp_table.id FROM $temp_table)");

		$this->before_query[] = function() use ($temp_table, $index_name, $value, $machine_filter) {
			$this->query->pdo->query("CREATE TEMPORARY TABLE $temp_table (`id` INT(11) NOT NULL)");

			$sq = $this->sphinx->select('*')->from($index_name)->where('MATCH(?)', $value);
			if (!empty($machine_filter['sphinx_option'])) {
				$sq->option($machine_filter['sphinx_option']);
			}
			// TODO: Add param. conditions here
			$sr = $sq->query();
			$ins = $this->query->pdo->prepare("INSERT INTO $temp_table (id) VALUES (:id)");
			foreach ($sr as $row) {
				$ins->execute(array('id' => $row['id']));
			}
		};

		$this->after_query[] = function() use ($temp_table) {
			$this->query->pdo->query("DROP TEMPORARY TABLE $temp_table");
		};
	}

}


