<?php
/*
 * Copyright (c) 2011, Josef Kufner  <jk@frozen-doe.net>
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

class M_entity__load extends Module {

	protected $inputs = array(
		'id' => false,		// primary key; output is list if empty
		'table' => array(),	// table name (in database)
		'order-by' => false,	// order by column
		'order-asc' => true,	// is order ascending ?
	);

	protected $outputs = array(
		'id' => true,		// id of found entity
		'item' => true,		// entity (if id set)
		'items' => true,	// list of entities (if id is not set)
		'table-cfg' => true,	// configuration for core/out/table
		'error' => true,	// nothing found (not done)
		'done' => true,		// something found (no error)
	);

	public function main()
	{
		$id = $this->in('id');
		$table = $this->in('table');

		// load table description
		$table_desc = dibi::query('SHOW COLUMNS FROM %n', $table);
		$table_cfg = array();

		// start query
		$q = @dibi::select()->from('%n', $table);

		// add all columns
		foreach ($table_desc as $col) {
			$q->select('%n', $col['Field']);

			if ($col['Type'] == 'datetime' && defined('DATE_FMT')) {
				$q->select('DATE_FORMAT(%n', $col['Field'], ', %s', DATE_FMT, ')')->as($col['Field'].'_fmt');
			}

			if ($id !== false && $col['Key'] == 'PRI') {
				$q->where('%n', $col['Field'], '= %s', $id);
				$id_col = $col['Field'];
			}

			if ($id === false) {
				$table_cfg[] = array(
					'title' => _($col['Field']),
					'type' => 'text',
					'key' => $col['Field'],
				);
			}
		}

		// order-by
		$order_by = $this->in('order-by');
		if ($order_by !== false) {
			$q->orderBy('%n', $order_by, $this->in('order-asc') ? 'ASC':'DESC');
		}

		// fetch data
		if ($id !== false) {
			$r = $q->fetch();
			$this->out('item', $r);
			$this->out('table-cfg', null);
		} else {
			$r = $q->fetchAll();
			$this->out('items', $r);
			$this->out('table-cfg', $table_cfg);
		}
		
		// set status outputs
		if (empty($r)) {
			$this->out('id', null);
			$this->out('error', true);
			$this->out('done', false);
		} else {
			$this->out('id', $id !== false ? $r[$id_col] : null);
			$this->out('error', false);
			$this->out('done', true);
		}
	}
}




