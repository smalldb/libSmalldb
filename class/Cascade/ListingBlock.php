<?php
/*
 * Copyright (c) 2014, Josef Kufner  <jk@frozen-doe.net>
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

namespace Smalldb\Cascade;

use Smalldb\Machine\AbstractMachine;

/**
 * List all state machine instances matching given filters.
 *
 * @see Smalldb::StateMachine::AbstractBackend::createListing()
 */
class ListingBlock extends BackendBlock
{

	/**
	 * Block inputs
	 */
	protected $inputs = array(
		'filters' => null,
		'*' => null,		// Filters
	);

	/**
	 * Block outputs
	 */
	protected $outputs = array(
		'list' => true,
		'properties' => true,
		'done' => true,
	);

	/**
	 * Block must be always executed.
	 */
	const force_exec = true;


	/**
	 * Block body
	 */
	public function main()
	{
		$filters = (array) $this->in('filters');
		foreach ($this->inputNames() as $input) {
			if ($input != 'filters') {
				$filters[$input] = $this->in($input);
			}
		}

		$listing = $this->smalldb->createListing($filters);

		$this->out('list', $listing->fetchAll());
		$this->out('properties', $listing->describeProperties());
		$this->out('done', true);
	}
}

