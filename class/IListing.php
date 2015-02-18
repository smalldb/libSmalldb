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
 * A prefered way to retrieve list of existing state machine instances.
 * 
 * Listing is created by AbstractMachine::createListing method, it should not 
 * be created directly.
 */
interface IListing
{

	/**
	 * Execute SQL query or do whatever is required to get this listing 
	 * populated.
	 */
	function query();


	/**
	 * Get description of all properties (columns) in the listing.
	 */
	function describeProperties();


	/**
	 * Returns an array of all items in the listing.
	 */
	function fetchAll();


	/**
	 * Get filter configuration (processed and filled with pagination
	 * data). This method should be called after query().
	 */
	function getProcessedFilters();

}

