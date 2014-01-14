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

class FlupdoCrudMachine extends FlupdoMachine
{

	protected function initializeMachine($args)
	{
		parent::initializeMachine($args);

		$this->table = (string) $args['table'];

		// Name of inputs and outputs with properties
		$io_name = (string) $args['io_name'];
		if ($io_name == '') {
			$io_name = 'item';
		}

		// fetch properties from database
		$r = $this->flupdo->select('*')
			->from($this->flupdo->quoteIdent($this->table))
			->where('FALSE')->limit(0)
			->query();
		$col_cnt = $r->columnCount();

		// build properties description
		$this->properties = array();
		for ($i = 0; $i < $col_cnt; $i++) {
			$cm = $r->getColumnMeta($i);
			$this->properties[$cm['name']] = array(
				'name' => $cm['name'],
				//'type' => $cm['native_type'], // do not include corrupted information
			);
		}

		// Exists state only
		$this->states = array(
			'exists' => array(
				'label' => _('Exists'),
				'description' => '',
			),
		);

		// Actions
		$this->actions = array(
			'create' => array(
				'description' => _('Create a new item'),
				'transitions' => array(
					'' => array(
						'targets' => array('exists'),
					),
				),
				'returns' => self::RETURNS_NEW_ID,
				'block' => array(
					'inputs' => array(
						$io_name => array()
					),
					'outputs' => array(
						'id' => 'return_value'
					),
				),
			),
			'edit' => array(
				'description' => _('Modify item'),
				'transitions' => array(
					'exists' => array(
						'targets' => array('exists'),
						'permissions' => array(
							'owner' => true
						),
					),
				),
				'block' => array(
					'inputs' => array(
						'id' => array(),
						$io_name => array()
					),
					'outputs' => array(
						'id' => 'id'
					),
				),
			),
			'delete' => array(
				'description' => _('Destroy item'),
				'transitions' => array(
					'exists' => array(
						'targets' => array(''),
						'permissions' => array(
							'owner' => true
						),
					),
				),
				'block' => array(
					'inputs' => array(
						'id' => array(),
					),
					'outputs' => array(
					),
				),
			),
		);
	}


	/**
	 * Add state column into select clause of the $query.
	 */
	protected function queryAddStateSelect($query)
	{
		// If row is found, then state is 'exists'
		$query->select('"exists" AS `state`');
	}


	/**
	 * Create
	 *
	 * $id may be null, then auto increment is used.
	 */
	protected function create($id, $properties)
	{
		// filter out unknown keys
		$properties = array_intersect_key($properties, $this->properties);

		if ($id !== null) {
			$properties = array_merge($properties, array_combine($this->describeId(), (array) $id));
		}

		// insert
		$n = $this->flupdo->insert(join(', ', $this->flupdo->quoteIdent(array_keys($properties))))
			->into($this->flupdo->quoteIdent($this->table))
			->values(array($properties))
			->debugDump()
			->exec();

		if ($n) {
			return $id === null ? $this->flupdo->lastInsertId() : $id;
		} else {
			return false;
		}
	}


	/**
	 * Edit
	 */
	protected function edit($id, $properties)
	{
		// filter out unknown keys
		$properties = array_intersect_key($properties, $this->properties);

		// build update query
		$q = $this->flupdo->update($this->flupdo->quoteIdent($this->table));
		$this->queryAddPrimaryKeyWhere($q, $id);
		foreach ($properties as $k => $v) {
			$q->set($q->quoteIdent($k).' = ?', $v);
		}

		$n = $q->debugDump()->exec();

		if ($n) {
			return true;
		} else {
			return false;
		}
	}


	/**
	 * Delete
	 */
	protected function delete($id)
	{
		// build update query
		$q = $this->flupdo->delete()->from($this->flupdo->quoteIdent($this->table));
		$this->queryAddPrimaryKeyWhere($q, $id);

		$n = $q->debugDump()->exec();

		if ($n) {
			return true;
		} else {
			return false;
		}
	}

}


