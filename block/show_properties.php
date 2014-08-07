<?php
/*
 * Copyright (c) 2012, Josef Kufner  <jk@frozen-doe.net>
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

class B_smalldb__show_properties extends \Cascade\Core\Block
{

	protected $inputs = array(
		'desc' => null,
		'slot' => 'default',
		'slot_weight' => 50,
	);

	protected $connections = array(
		'desc' => array(),
	);

	protected $outputs = array(
		'done' => true,
	);

	const force_exec = true;


	public function main()
	{
		$desc = $this->in('desc');
		if (!$desc) {
			return;
		}

		$table = new \Cascade\Core\TableView();

		$table->addColumn('text', array(
				'title' => _('PK'),
				'title_tooltip' => _('Primary key'),
				'value' => function($row) use ($desc) { return in_array($row['name'], $desc['primary_key']) ? _("\xE2\x97\x8F") : ''; },
				'width' => '1%',
			));
		$table->addColumn('text', array(
				'title' => _('Property'),
				'key' => 'name',
			));
		$table->addColumn('text', array(
				'title' => _('Type'),
				'key' => 'type',
			));
		$table->addColumn('number', array(
				'title' => _('Size'),
				'value' => function($row) { return isset($row['size']) && $row['size'] > 0 ? $row['size'] : null; },
				'width' => '1%',
			));
		$table->addColumn('text', array(
				'title' => _('Default value'),
				'key' => 'default',
			));
		$table->addColumn('text', array(
				'title' => _('Optional'),
				'value' => function($row) { return isset($row['optional']) ? ($row['optional'] ? _('Yes') : _('No')) : ''; },
			));

		// Fill in property names since array keys are not passed to table row
		$properties = $desc['properties'];
		foreach ($properties as $pi => & $p) {
			if (!isset($p['name'])) {
				$p['name'] = $pi;
			}
		}

		$table->setData($properties);
                $this->templateAdd(null, 'core/table', $table);
                $this->out('done', true);		
	}

}


