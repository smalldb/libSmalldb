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
class FlupdoGenericListing implements IListing
{

	protected $machine;	///< Parent state machine, which created this listing.
	protected $query;	///< SQL query to execute.
	protected $result;	///< PDOStatement, result of the query.


	/**
	 * Prepare query builder
	 */
	public function __construct(AbstractMachine $machine, \Smalldb\Flupdo\SelectBuilder $query_builder, $query_filters, $machine_filters, $machine_properties)
	{
		$this->machine = $machine;

		// Prepare query builder
		$this->query = $query_builder;

		// Limit & offset
		if (isset($query_filters['limit']) && !isset($machine_filters['limit'])) {
			$this->query->limit((int) $query_filters['limit']);
		}
		if (isset($query_filters['offset']) && !isset($machine_filters['offset'])) {
			$this->query->offset(max(0, (int) $query_filters['offset']));
		}

		// Ordering -- it is first, so it overrides other filters
		if (isset($query_filters['order-by']) && !isset($machine_filters['order-by'])) {
			$this->query->orderBy($this->query->quoteIdent($query_filters['order-by'])
				.(isset($query_filters['order-asc']) && !$query_filters['order-asc'] ? ' DESC' : ' ASC'));
		}

		// Add filters
		foreach($query_filters as $property => $value) {
			if (isset($machine_filters[$property])) {
				foreach ($machine_filters[$property] as $f) {
					$args = array($f['sql']);
					foreach ($f['params'] as $p) {
						$args[] = $query_filters[$p];
					}
					call_user_func_array(array($this->query, $f['stmt']), $args);
				}
			} else {
				$property = str_replace('-', '_', $property);

				if (isset($machine_properties[$property])) {
					// Filter name matches property name => is value equal ?
					$this->query->where($this->query->quoteIdent($property).' = ?', $value);
				} else {
					// Check if operator is the last character of filter name
					$operator = substr($property, -1);
					if (!preg_match('/^([^><!%~:]+)([><!%~:]+)$/', $property, $m)) {
						continue;
					}
					list(, $property, $operator) = $m;
					if (!isset($machine_properties[$property])) {
						continue;
					}

					// Do not forget there is '=' after the operator in URL.
					$p = $this->query->quoteIdent($property);
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
			}
			catch (\Smalldb\Flupdo\FlupdoSqlException $ex) {
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
	 */
	public function fetchAll()
	{
		if ($this->result === null) {
			$this->query();
		}
		return $this->result->fetchAll(\PDO::FETCH_ASSOC);
	}


}


