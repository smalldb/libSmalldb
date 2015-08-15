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

	/// Nested-sets configuration
	protected $nested_sets_table_columns = array(
		'id' => 'id',
		'parent_id' => 'parent_id',
		'left' => 'tree_left',
		'right' => 'tree_right',
		'depth' => 'tree_depth',
	);

	/// Enable nested-sets tree?
	protected $nested_sets_enabled = false;

	/// Order by this column
	protected $nested_sets_order_by = 'id';

	/// Set this column to NOW() on create transition
	protected $time_created_table_column = null;
	/// Set this column to NOW() on edit transition. If MySQL is in use, it is better to use CURRENT_TIMESTAMP column feature.
	protected $time_modified_table_column = null;

	/**
	 * @copydoc FlupdoMachine::initializeMachine()
	 */
	protected function initializeMachine($config)
	{
		parent::initializeMachine($config);

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

		// create time column
		if (isset($config['time_created_table_column'])) {
			$this->time_created_table_column = $config['time_created_table_column'];
		}

		// modification time column
		if (isset($config['time_modified_table_column'])) {
			$this->time_modified_table_column = $config['time_modified_table_column'];
		}

		// nested-sets configuration
		if (isset($config['nested_sets'])) {
			$ns = $config['nested_sets'];
			if (isset($ns['table_columns'])) {
				$this->nested_sets_table_columns = $ns['table_columns'];
			}
			if (isset($ns['order_by'])) {
				$this->nested_sets_order_by = $ns['order_by'];
			}
			if (isset($ns['enabled'])) {
				$this->nested_sets_enabled = $ns['enabled'];
			}
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

		$this->setupDefaultMachine($config);
	}


	/**
	 * Setup basic CRUD machine.
	 */
	protected function setupDefaultMachine($config)
	{
		// Name of inputs and outputs with properties
		$io_name = isset($config['io_name']) ? (string) $config['io_name'] : 'item';

		// Create default transitions?
		$no_default_transitions = !empty($config['crud_machine_no_default_transitions']);

		// Exists state only
		$this->states = $no_default_transitions ? array() : array(
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
				'transitions' => $no_default_transitions ? array() : array(
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
				'transitions' => $no_default_transitions ? array() : array(
					'exists' => array(
						'targets' => array('exists'),
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
				'weight' => 80,
				'transitions' => $no_default_transitions ? array() : array(
					'exists' => array(
						'targets' => array(''),
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

		// Merge with config
		if (isset($config['states'])) {
			$this->states = array_replace_recursive($this->states, $config['states']);
		}

		// Simple 'exists' state if not state select is not defined
		if ($this->state_select === null) {
			$this->state_select = '"exists"';
		}
	}


	/**
	 * Create
	 *
	 * $ref may be nullRef, then auto increment is used.
	 */
	protected function create(Reference $ref, $properties)
	{
		// filter out unknown keys
		$properties = array_intersect_key($properties, $this->properties);

		if (!$ref->isNullRef()) {
			$properties = array_merge($properties, array_combine($this->describeId(), (array) $ref->id));
		}

		// Set times
		if ($this->time_created_table_column) {
			$properties[$this->time_created_table_column] = new \Flupdo\Flupdo\FlupdoRawSql('NOW()');
		}
		if ($this->time_modified_table_column) {
			$properties[$this->time_modified_table_column] = new \Flupdo\Flupdo\FlupdoRawSql('NOW()');
		}


		if (empty($properties)) {
			throw new \InvalidArgumentException('No valid properties provided.');
		}

		// Set owner
		if ($this->user_id_table_column && ($a = $this->user_id_auth_method)) {
			$properties[$this->user_id_table_column] = $this->backend->getAuth()->$a();
		}

		// Check permission of owning machine
		if ($this->owner_relation && $this->owner_create_transition) {
			$ref_ref = $this->resolveMachineReference($this->owner_relation, $properties);
			if (!$ref_ref->machine->isTransitionAllowed($ref_ref, $this->owner_create_transition)) {
				throw new \RuntimeException(sprintf(
					'Permission denied to create machine %s because transition %s of %s is not allowed.',
					$this->machine_type, $this->owner_create_transition, $ref->machine_type
				));
			}
		}

		// insert
		$data = $this->encodeProperties($properties);
		$n = $this->flupdo->insert(join(', ', $this->flupdo->quoteIdent(array_keys($data))))
			->into($this->flupdo->quoteIdent($this->table))
			->values(array($data))
			->debugDump()
			->exec();

		if (!$n) {
			// Insert failed
			return false;
		}

		// Return ID of inserted row
		if ($ref->isNullRef()) {
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
		} else {
			$id = $ref->id;
		}

		if ($this->nested_sets_enabled) {
			$this->recalculateTree();
		}

		return $id;
	}


	/**
	 * Edit
	 */
	protected function edit(Reference $ref, $properties)
	{
		// filter out unknown keys
		$properties = array_intersect_key($properties, $this->properties);

		if (empty($properties)) {
			throw new \InvalidArgumentException('No valid properties provided.');
		}

		// Set modification time
		if ($this->time_modified_table_column) {
			$properties[$this->time_modified_table_column] = new \Flupdo\Flupdo\FlupdoRawSql('NOW()');
		}

		// build update query
		$q = $this->flupdo->update($this->queryGetThisTable($this->flupdo));
		$this->queryAddPrimaryKeyWhere($q, $ref->id);
		foreach ($this->encodeProperties($properties) as $k => $v) {
			$q->set($q->quoteIdent($k).' = ?', $v);
		}

		$n = $q->debugDump()->exec();

		if ($n !== FALSE) {
			if ($this->nested_sets_enabled) {
				$this->recalculateTree();
			}
			return true;
		} else {
			return false;
		}
	}


	/**
	 * Delete
	 */
	protected function delete(Reference $ref)
	{
		// build update query
		$q = $this->flupdo->delete()->from($this->flupdo->quoteIdent($this->table));
		$this->queryAddPrimaryKeyWhere($q, $ref->id);

		$n = $q->debugDump()->exec();

		if ($n) {
			return true;
		} else {
			return false;
		}
	}


	/**
	 * Recalculate nested-sets tree indices
	 *
	 * To use this feature a parent, left, right and depth columns must be specified.
	 *
	 * Composed primary keys are not supported yet.
	 *
	 * Three extra columns are required: tree_left, tree_right, tree_depth
	 * (ints, all nullable). This function will update them according to id
	 * and parent_id columns.
	 */
	protected function recalculateTree()
	{
		if (!$this->nested_sets_enabled) {
			throw new \RuntimeException('Nested sets are disabled for this entity.');
		}

		$q_table     = $this->flupdo->quoteIdent($this->table);
		$cols        = $this->nested_sets_table_columns;
		$c_order_by  = $this->nested_sets_order_by;
		$c_id        = $this->flupdo->quoteIdent($cols['id']);
		$c_parent_id = $this->flupdo->quoteIdent($cols['parent_id']);
		$c_left      = $this->flupdo->quoteIdent($cols['left']);
		$c_right     = $this->flupdo->quoteIdent($cols['right']);
		$c_depth     = $this->flupdo->quoteIdent($cols['depth']);

		$set = $this->flupdo->select($c_id)
			->from($q_table)
			->where("$c_parent_id IS NULL")
			->orderBy($c_order_by)
			->query();

		$this->recalculateSubTree($set, 1, 0, $q_table, $c_order_by, $c_id, $c_parent_id, $c_left, $c_right, $c_depth);
	}


	/**
	 * Recalculate given subtree.
	 *
	 * @see recalculateTree()
	 */
	private function recalculateSubTree($set, $left, $depth, $q_table, $c_order_by, $c_id, $c_parent_id, $c_left, $c_right, $c_depth)
	{
		foreach($set as $row) {
			$id = $row['id'];
			
			$this->flupdo->update($q_table)
				->set("$c_left = ?", $left)
				->set("$c_depth = ?", $depth)
				->where("$c_id = ?", $id)
				->exec();

			$sub_set = $this->flupdo->select($c_id)
				->from($q_table)
				->where("$c_parent_id = ?", $id)
				->orderBy($c_order_by)
				->query();

			$left = $this->recalculateSubTree($sub_set, $left + 1, $depth + 1,
					$q_table, $c_order_by, $c_id, $c_parent_id, $c_left, $c_right, $c_depth);
			
			$this->flupdo->update($q_table)
				->set("$c_right = ?", $left)
				->where("$c_id = ?", $id)
				->exec();

			$left++;
		}
		return $left;
	}

}


