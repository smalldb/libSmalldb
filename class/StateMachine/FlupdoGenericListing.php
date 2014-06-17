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
 */
class FlupdoGenericListing implements IListing
{

	protected $machine;	///< Parent state machine, which created this listing.
	protected $query;	///< SQL query to execute.
	protected $result;	///< PDOStatement, result of the query.


	/**
	 * Prepare query builder
	 */
	public function __construct($machine, $flupdo)
	{
		$this->machine = $machine;
		$this->query = new \Smalldb\Flupdo\SelectBuilder($flupdo);
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
			$this->result = $this->query->query();
		} else {
			throw new RuntimeException('Query already performed.');
		}
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
	function fetchAll()
	{
		if ($this->result === null) {
			$this->query();
		}
		return $this->result->fetchAll(\PDO::FETCH_ASSOC);
	}


}


