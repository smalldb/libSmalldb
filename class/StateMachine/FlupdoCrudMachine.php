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
 * Simple state machine for typical CRUD entities accessed via Flupdo.
 */
class FlupdoCrudMachine extends FlupdoMachine
{
	/// Relation defining shich machine owns this machine
	protected $owner_relation = null;

	/// Transition of owner to check when creating this machine
	protected $owner_create_transition = null;


	/**
	 * @copydoc FlupdoMachine::initializeMachine()
	 */
	protected function initializeMachine($config)
	{
		parent::initializeMachine($config);

		// Name of inputs and outputs with properties
		$io_name = (string) $config['io_name'];
		if ($io_name == '') {
			$io_name = 'item';
		}

		// user_id table column & auth property
		if (isset($config['user_id_table_column'])) {
			$this->user_id_table_column = $config['user_id_table_column'];
		}
		if (isset($config['user_id_auth_method'])) {
			$this->user_id_auth_method = $config['user_id_auth_method'];
		}

		// owner relation
		if (isset($config['owner_relation'])) {
			$this->owner_relation = $config['owner_relation'];
		}
		if (isset($config['owner_create_transition'])) {
			$this->owner_create_transition = $config['owner_create_transition'];
		}

		// properties
		if (!empty($config['properties'])) {
			// if properties are difined manualy, use them
			$this->properties = $config['properties'];
			$this->pk_columns = array();
			foreach ($this->properties as $property => $p) {
				if (!empty($p['is_pk'])) {
					$this->pk_columns[] = $property;
				}
			}
		} else {
			$this->scanTableColumns();
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
				'label' => _('Create'),
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
						'ref' => 'return_value'
					),
				),
			),
			'edit' => array(
				'label' => _('Edit'),
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
						'ref' => array(),
						$io_name => array()
					),
					'outputs' => array(
						'ref' => 'ref'
					),
				),
			),
			'delete' => array(
				'label' => _('Delete'),
				'description' => _('Delete item'),
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
						'ref' => array(),
					),
					'outputs' => array(
					),
				),
			),
		);

		// Merge with config
		if (isset($config['actions'])) {
			$this->actions = array_replace_recursive($this->actions, $config['actions']);
		}

		// Simple 'exists' state if not state select is not defined
		if ($this->state_select === null) {
			$this->state_select = '"exists"';
		}
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

		// Set owner
		if ($this->user_id_table_column && ($a = $this->user_id_auth_method)) {
			$properties[$this->user_id_table_column] = $this->backend->getAuth()->$a();
		}

		// Check permission of owning machine
		if ($this->owner_relation && $this->owner_create_transition) {
			$this->resolveMachineReference($this->owner_relation, $properties, $ref_machine_type, $ref_machine_id);
			if (!$this->backend->getMachine($ref_machine_type)->isTransitionAllowed($ref_machine_id, $this->owner_create_transition)) {
				throw new \RuntimeException(sprintf(
					'Permission denied to create machine %s because transition %s of %s is not allowed.',
					$this->machine_type, $this->owner_create_transition, $ref_machine_type
				));
			}
		}

		// insert
		$n = $this->flupdo->insert(join(', ', $this->flupdo->quoteIdent(array_keys($properties))))
			->into($this->flupdo->quoteIdent($this->table))
			->values(array($this->encodeProperties($properties)))
			->debugDump()
			->exec();

		if (!$n) {
			// Insert failed
			return false;
		}

		// Return ID of inserted row
		if ($id === null) {
			$id_keys = $this->describeId();
			$id = array();
			foreach ($id_keys as $k) {
				if (isset($properties[$k])) {
					$id[] = $properties[$k];
				} else {
					// If part of ID is missing, it must be autoincremented
					// column, otherwise the insert would have failed.
					$id[] = $this->flupdo->lastInsertId();
				}
			}
		}
		return $id;
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
		foreach ($this->encodeProperties($properties) as $k => $v) {
			$q->set($q->quoteIdent($k).' = ?', $v);
		}

		$n = $q->debugDump()->exec();

		if ($n !== FALSE) {
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


