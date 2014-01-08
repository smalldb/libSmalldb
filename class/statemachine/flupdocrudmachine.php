<?php
/*
 * Copyright (c) 2013, Josef Kufner  <jk@frozen-doe.net>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. Neither the name of the author nor the names of its contributors
 *    may be used to endorse or promote products derived from this software
 *    without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE REGENTS AND CONTRIBUTORS ``AS IS'' AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED.  IN NO EVENT SHALL THE REGENTS OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
 * OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
 * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
 * SUCH DAMAGE.
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
	}


	/**
	 * Delete
	 */
	protected function delete($id)
	{
	}

}


