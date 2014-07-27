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

/**
 * Simple menu to make all entities accessible.
 */
class EntityMenuBlock extends BackendBlock
{

	/**
	 * Block inputs
	 */
	protected $inputs = array(
		'slot' => 'default',
		'slot_weight' => 50,
	);

	/**
	 * Block outputs
	 */
	protected $outputs = array(
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
		$menu = array();

		foreach ($this->smalldb->getKnownTypes() as $entity) {
			$m = $this->smalldb->getMachine($entity);
			$a = $m->describeMachineAction('listing');
			if (isset($a['heading'])) {
				$menu[$entity] = array(
					'label' => $a['heading'],
					'link' => '/'.$entity,
				);
			}
		}

		$this->templateAdd(null, 'smalldb/entity_menu', array(
			'menu' => $menu,
			'expanded' => false,
		));
	}

}

