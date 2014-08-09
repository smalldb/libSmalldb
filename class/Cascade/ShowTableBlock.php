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
 * Show listing in simple table.
 *
 * @see ListingBlock.
 */
class ShowTableBlock extends BackendBlock
{

	/**
	 * Block inputs
	 */
	protected $inputs = array(
		'list' => null,
		'properties' => null,
		'slot' => 'default',
		'slot_weight' => 50,
	);

	/**
	 * Block outputs
	 */
	protected $outputs = array(
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
		$list = $this->in('list');
		$properties = $this->in('properties');

		$table = new \Cascade\Core\TableView();

		//debug_dump($properties);

		// TODO: Add action buttons

		if (!empty($properties)) {
			foreach ($properties as $property => $p) {
				$col_opts = array(
					'title'  => isset($p['name']) ? $p['name'] : $p['label'],
					'key'    => $property,
				);
				if (!empty($p['is_pk'])) {
					$col_opts['link'] = '#';	// FIXME
				}
				$table->addColumn('text', array_merge($p, $col_opts));
			}
		} else if (!empty($list)) {
			foreach (reset($list) as $k => $v) {
				$table->addColumn('text', array(
						'title'  => $k,
						'key'    => $k,
					));
			}
		} else {
			// no columns, no rows
			return;
		}

		$table->setData($list);
	
		$this->templateAdd(null, 'core/table', $table);

		$this->out('done', true);
	}
}

