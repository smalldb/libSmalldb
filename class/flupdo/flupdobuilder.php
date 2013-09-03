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
	protected $pdo;

	protected $clause_tree = null;
	protected $buffers = array();
	protected $clause_stack = array();
	protected $buffer_stack = array();


	public function __construct(\PDO $pdo, & $clause_tree)
	{
		$this->clause_tree = $clause_tree;

		$this->pdo = $pdo;
		$this->clause_stack[] = & $this->clause_tree;
	}


	public function __call($method, $args)
	{
		echo __CLASS__, "::", $method, " (", join(', ', array_map(function($x) { return var_export($x, true);}, $args)), ")\n";

		$cur_clause = end($this->clause_stack);

		while (!isset($cur_clause[$method]) && !empty($this->clause_stack)) {
			array_pop($this->clause_stack);
			$cur_clause = end($this->clause_stack);
			echo "  - Method not found ... pop!\n";
		}

		if (empty($this->clause_stack)) {
			echo "  - Stack is empty!\n";
			throw new \BadMethodCallException('Unknown method "'.$method.'".');
		}

		echo "  - Method ok.\n";
		$this->clause_stack[] = & $cur_clause[$method];

		list($n) = $this->clause_stack;
		echo "  - Node ID: ", $n['#'], "\n";
		echo "\n";
		return $this;
	}


	public function __toString()
	{
		return 'SELECT NULL -- ('.__CLASS__.') --';
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

