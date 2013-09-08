<?php
/*
 * Copyright (c) 2012, Josef Kufner  <jk@frozen-doe.net>
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

namespace Entity;

/**
 * DibiEntityDriver uses Dibi to access MySQL database and expects that Dibi is
 * propperly initialized. It maps every row in specified table to one entity.
 *
 * This is not supposed to be fully featured ORM, but rather simple way to
 * manage simple records (which are 90% of web applications). If you need to
 * implement more complex entities, write your own driver.
 */
trait DibiEntityDriver {

	/**
	 * Initialize this driver before use.
	 */
	protected function initializeEntityDriver()
	{
		if (!isset($this->entity_table)) {
			error_msg('No SQL table specified! Set $entity_table property.');
			return false;
		}

		if (!class_exists('dibi')) {
			error_msg('Dibi	is not initialized!');
			return false;
		}

		return true;
	}


	/**
	 * Clean all used resources after use.
	 */
	protected function cleanupEntityDriver()
	{
	}


	/**
	 * Get full description of the entity.
	 */
	protected function describeEntity()
	{
		$r = \dibi::select('COLUMN_NAME as `name`, IS_NULLABLE as `is_null`, DATA_TYPE as `type`')
			->select('(COLUMN_KEY = "PRI") as `primary_key`')
			->select('(COLUMN_KEY != "") as `has_index`')
			->select('COLUMN_COMMENT as `comment`')
			->from('information_schema.columns')
			->where('table_name = %s', $this->entity_table);

		return array(
			'name' => _('Entity'),
			'driver' => __TRAIT__,
			'comment' => _('Basic entity.'),
			'table' => $this->entity_table,
			'properties' => $r->fetchAll(),
		);
	}


	/**
	 * Create specified entity, return it's ID.
	 */
	protected function createEntity($e)
	{
		// TODO
		return false;
	}


	/**
	 * Load entity by specified filters. Returns list of entities.
	 */
	protected function readEntity($filters)
	{
		$q = \dibi::select('*')
			->from($this->entity_table);

		return $q->fetchAll();
	}


	/**
	 * Update specified entity.
	 */
	protected function updateEntity($id, $e)
	{
		// TODO
		return false;
	}


	/**
	 * Delete entity by primary key.
	 */
	protected function deleteEntity($id)
	{
		// TODO
		return false;
	}

}

