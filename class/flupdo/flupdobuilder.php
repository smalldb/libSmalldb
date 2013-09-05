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

namespace Smalldb\Flupdo;

class FlupdoBuilder
{
	/**
	 * PDO driver used to execute query and escape strings.
	 */
	protected $pdo;

	protected $indent = "\t";
	protected $sub_indent = "\t\t";

	/**
	 * Built query
	 */
	protected $query_sql = null;
	protected $query_params = null;

	/**
	 * List of clauses used to composed result query. Shared constant data.
	 */
	protected static $clauses = array();

	/**
	 * List of methods used to fill the $buffers. Shared constant data.
	 */
	protected static $methods = array();

	/**
	 * Buffers containing SQL fragments.
	 */
	protected $buffers = array();



	public function __construct(\PDO $pdo)
	{
		$this->pdo = $pdo;
	}


	public function __call($method, $args)
	{
		//echo __CLASS__, "::", $method, " (", join(', ', array_map(function($x) { return var_export($x, true);}, $args)), ")\n";

		if (!isset(static::$methods[$method])) {
			throw new \BadMethodCallException('Undefined method "'.$method.'".');
		}

		@ list($action, $buffer_id, $label) = static::$methods[$method];

		$this->$action($args, $buffer_id, $label);

		return $this;
	}


	/**
	 * Add SQL fragment to buffer.
	 */
	protected function add($args, $buffer_id)
	{
		$this->buffers[$buffer_id][] = $args;
	}


	/**
	 * Replace buffer content with SQL fragment.
	 */
	protected function replace($args, $buffer_id)
	{
		$this->buffers[$buffer_id] = array($args);
	}


	/**
	 * Set flag. Replace buffer with new label of this flag.
	 */
	protected function setFlag($args, $buffer_id, $label)
	{
		$this->buffers[$buffer_id] = $label;
	}


	/**
	 * Add join statement to buffer.
	 */
	protected function addJoin($args, $buffer_id, $label)
	{
		$this->buffers[$buffer_id][] = $args;
	}


	/**
	 * Process all buffers and build SQL query. Side product is array of 
	 * parameters (stored in $this->args) to bind with query.
	 */
	protected function compile()
	{
		$this->sqlStart();

		echo 'SELECT NULL -- ('.__CLASS__.') --';

		$this->sqlFinish();
	}


	public function __toString()
	{
		if ($this->query_sql === null) {
			$this->compile();
		}
		return $this->query_sql;
	}


	protected function sqlStart()
	{
		$this->query_params = array();
		ob_start();
	}


	protected function sqlFinish()
	{
		$this->query_sql = ob_get_clean();
	}


	protected function sqlComment($buffer_id)
	{
		if (isset($this->buffers[$buffer_id])) {
			foreach ($this->buffers[$buffer_id] as $buf) {
				echo $this->indent, '-- ', str_replace(array("\r", "\n"), array('', "\n".$this->indent.'-- '), $buf[0]), "\n";
			}
		}
	}


	protected function sqlFlag($buffer_id)
	{
		if (isset($this->buffers[$buffer_id])) {
			if (isset($this->buffers[$flag_buf])) {
				echo ' ', $this->buffers[$flag_buf];
			}
		}
	}


	protected function sqlStatement_Flags_NoEol($buffer_id, $flag_buffer_ids)
	{
		if (isset($this->buffers[$buffer_id])) {
			echo $this->indent, $buffer_id;
			foreach ($flag_buffer_ids as $flag_buf) {
				if (isset($this->buffers[$flag_buf])) {
					echo ' ', $this->buffers[$flag_buf];
				}
			}
		}
	}


	protected function sqlSpace_PlainList_Eol($buffer_id)
	{
		$first = true;

		if (isset($this->buffers[$buffer_id])) {
			foreach ($this->buffers[$buffer_id] as $buf) {
				if ($first) {
					$first = false;
					echo ' ';
				} else {
					echo ",\n", $this->sub_indent;
				}
				echo $buf[0];
			}
		}
		echo "\n";
	}


	protected function sqlList($buffer_id)
	{
		$first = true;

		if (isset($this->buffers[$buffer_id])) {
			echo $this->indent, $buffer_id;
			foreach ($this->buffers[$buffer_id] as $buf) {
				if ($first) {
					$first = false;
					echo ' ';
				} else {
					echo ",\n", $this->sub_indent;
				}
				echo $buf[0];
			}
			echo "\n";
		}
	}


	protected function sqlConditions($buffer_id)
	{
		$first = true;

		if (isset($this->buffers[$buffer_id])) {
			echo $this->indent, $buffer_id;
			foreach ($this->buffers[$buffer_id] as $buf) {
				if ($first) {
					$first = false;
					echo ' (';
				} else {
					echo $this->sub_indent, "AND (";
				}
				echo $buf[0], ")\n";
			}
		}
	}


	protected function sqlStatement($buffer_id)
	{
		if (isset($this->buffers[$buffer_id])) {
			echo $this->indent, $buffer_id, " ";
			echo $this->buffers[$buffer_id][0][0];
			echo "\n";
		}
	}


	/**
	 * Quotes a string for use in a query.
	 *
	 * Proxy to PDO::quote().
	 */
	public function quote($value)
	{
		return $this->pdo->quote($value);
	}


	/**
	 * Builds and executes an SQL statement, returning the number of affected rows.
	 *
	 * Proxy to PDO::exec().
	 */
	public function exec()
	{
		return $this->pdo->exec((string) $this);
	}


	/**
	 * Builds and executes an SQL statement, returning a result set as a PDOStatement object.
	 *
	 * Proxy to PDO::query().
	 */
	public function query()
	{
		$args = func_get_args();
		array_unshift($args, (string) $this);
		return call_user_func_array(array($this->pdo, 'query'), $args);
	}


	/**
	 * Builds and prepares a statement for execution, returns a statement object.
	 *
	 * Proxy to PDO::prepare().
	 */
	public function prepare($driver_options = array())
	{
		return $this->pdo->prepare((string) $this, $driver_options);
	}

}

