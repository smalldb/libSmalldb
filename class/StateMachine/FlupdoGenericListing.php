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
 *   - `order-by` (property/column name; default = undefined)
 *   - `order-asc` (bool; default = true; applied only when order-by is set)
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

	protected $machine;		///< Parent state machine, which created this listing.
	protected $query;		///< SQL query to execute.
	protected $result;		///< PDOStatement, result of the query.
	protected $query_filters;	///< Actual filters.


	/**
	 * Prepare query builder
	 *
	 * @param $machine State machine implementation to inspect.
	 * @param $query_builder Query builder where filters will be added.
	 * @param $query_filters Requested filters to add to $query_builder.
	 * @param $machine_filters Custom filter definitions (how things should be filtered, not filtering itself).
	 * @param $machine_properties State machine properties definitions.
	 * @param $machine_references State machine references to other state machines.
	 */
	public function __construct(AbstractMachine $machine, \Flupdo\Flupdo\SelectBuilder $query_builder, $query_filters,
		$machine_table, $machine_filters, $machine_properties, $machine_references)
	{
		$this->machine = $machine;
		$this->query_filters = $query_filters;

		// Prepare query builder
		$this->query = $query_builder;
		$machine_table = $this->query->quoteIdent($machine_table);

		// Limit & offset
		if (isset($query_filters['limit']) && !isset($machine_filters['limit'])) {
			if ($query_filters['limit'] !== false) {	// false == infinity
				$this->query->limit((int) $query_filters['limit']);
			}
		} else {
			// Fail-safe
			$this->query->limit(100);
		}
		if (isset($query_filters['offset']) && !isset($machine_filters['offset'])) {
			$this->query->offset(max(0, (int) $query_filters['offset']));
		}

		// Ordering -- it is first, so it overrides other filters
		if (isset($query_filters['order_by']) && !isset($machine_filters['order_by'])) {
			$this->query->orderBy($this->query->quoteIdent($query_filters['order_by'])
				.(!isset($query_filters['order_asc']) || !$query_filters['order_asc'] ? ' DESC' : ' ASC'));
		} else if (isset($query_filters['order-by']) && !isset($machine_filters['order-by'])) {
			$this->query->orderBy($this->query->quoteIdent($query_filters['order-by'])
				.(!isset($query_filters['order-asc']) || !$query_filters['order-asc'] ? ' DESC' : ' DESC'));
		}

		// Add filters
		foreach($query_filters as $filter_name => $value) {
			$filter_name = str_replace('-', '_', $filter_name);
			if (isset($machine_filters[$filter_name])) {
				// Custom filter
				if (isset($machine_filters[$filter_name]['query_map'][$value])) {
					// Check query_map for the value
					$filters = $machine_filters[$filter_name]['query_map'][$value];
				} else if (isset($machine_filters[$filter_name]['query'])) {
					// Fallback if query_map does not contain value or does not exist at all
					$filters = $machine_filters[$filter_name]['query'];
				} else {
					// No filter here.
					continue;
				}

				// Add filter to query builder
				foreach ($filters as $f) {
					$args = array($f['sql']);
					foreach ($f['params'] as $p) {
						$args[] = $query_filters[$p];
					}
					call_user_func_array(array($this->query, $f['stmt']), $args);
				}
			} else {
				// Use default property filter
				$property = str_replace('-', '_', $filter_name);

				if (isset($machine_properties[$property])) {
					// Filter name matches property name => is value equal ?
					$this->query->where($machine_table.'.'.$this->query->quoteIdent($property).' = ?', $value);
				} else if (isset($machine_references[$property])) {
					// Filter by reference
					if ($value->id === null) {
						throw new \InvalidArgumentException('Cannot filter by null ref.');
					}
					if ($value->machine_type != $machine_references[$property]['machine_type']) {
						throw new \InvalidArgumentException('Referenced machine type does not match reference machine type.');
					}

					// Add where clause for each ID fragment
					$id_properties = $machine_references[$property]['machine_id'];
					$id_values = (array) $value->id;
					$id_parts = count($id_properties);
					for ($i = 0; $i < $id_parts; $i++) {
						$this->query->where($machine_table.'.'.$this->query->quoteIdent($id_properties[$i]).' = ?', $id_values[$i]);
					}
				} else {
					// Check if operator is the last character of filter name
					$operator = substr($property, -1);
					if (!preg_match('/^([^><!%~:?]+)([><!%~:?]+)$/', $property, $m)) {
						continue;
					}
					list(, $property, $operator) = $m;
					if (!isset($machine_properties[$property])) {
						continue;
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
								if (isset($value['min']) && isset($value['max'])) {
									$this->query->where("? <= $p AND $p < ?", $value['min'], $value['max']);
								} else {
									$this->query->where("? <= $p AND $p < ?", $value[0], $value[1]);
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
								} else {
									$this->query->where("$p < ? OR ? <= $p", $value[0], $value[1]);
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
							$this->query->where($this->query->quoteIdent($property).' REGEXP ?', $value);
							break;
						case '!~':
							$this->query->where($this->query->quoteIdent($property).' NOT REGEXP ?', $value);
							break;
						case '%':
							$this->query->where($this->query->quoteIdent($property).' LIKE ?', $value);
							break;
						case '!%':
							$this->query->where($this->query->quoteIdent($property).' NOT LIKE ?', $value);
							break;
					}
				}
			}
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


	/**
	 * Get description of all properties (columns) in the listing.
	 */
	public function describeProperties()
	{
		return $this->machine->describeAllMachineProperties();
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
		return array_map(array($this->machine, 'decodeProperties'), $this->result->fetchAll(\PDO::FETCH_ASSOC));
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
	function getProcessedFilters()
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
			return $filters;
		} else {
			return $this->query_filters;
		}
	}

}


