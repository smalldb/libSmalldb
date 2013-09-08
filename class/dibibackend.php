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

namespace Smalldb;

/**
 * Dibi Smalldb backend expects dibi library initialized and global static
 * class dibi working.
 */
class DibiBackend extends AbstractBackend
{
	private $dbinfo;


	public function __construct($alias)
	{
		parent::__construct($alias);
		$this->dbinfo = \dibi::getDatabaseInfo();
	}


	/**
	 * Get all known types.
	 */
	public function getKnownTypes()
	{
		return array_map(array($this, 'tableToType'), $this->dbinfo->getTableNames());
	}


	/**
	 * Convert table name to type name.
	 *
	 * No conversion by default, types match database tables 1:1.
	 */
	protected function tableToType($table)
	{
		return $table;
	}


	/**
	 * Convert type name to table name.
	 *
	 * No conversion by default, types match database tables 1:1.
	 */
	protected function typeToTable($type)
	{
		return $type;
	}


	/**
	 * Describe properties of specified state machine type.
	 */
	public function describe($type)
	{
		$table = $this->typeToTable($type);
		if (!$this->dbinfo->hasTable($table)) {
			return false;
		}

		$info = $this->dbinfo->getTable($table);

		// Get properties
		$properties = array();
		foreach($info->getColumns() as $col) {
			$properties[$col->getName()] = array(
				'name' => $col->getName(),
				'type' => $col->getNativeType(),
				'size' => $col->getSize(),
				'default' => $col->getDefault(),
				'optional' => $col->isNullable(),
			);
		}

		// Get primary key
		$pkinfo = $info->getPrimaryKey();
		if ($pkinfo) {
			$pk = array();
			foreach ($pkinfo->getColumns() as $col) {
				$pk[] = $col->getName();
			}
		} else {
			$pk = null;
		}

		return array(
			'type' => $type,
			'table' => $table,
			'primary_key' => $pk,
			'properties' => $properties,
		);
	}


	/**
	 * Get list of available actions for given type and state.
	 */
	public function getActions($type, $state)
	{
	}


	/**
	 * DELETE ME
	 */
	protected function createMachine($type) {}
}

