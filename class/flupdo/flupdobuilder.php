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


	const INDENT		= 0x01;
	const LABEL		= 0x02;
	const BRACKETS		= 0x04;
	const EOL		= 0x80;
	const ALL_DECORATIONS	= 0xFF;


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

		if ($this->query_sql !== null) {
			throw new \RuntimeException('Query is already compiled.');
		}

		@ list($action, $buffer_id, $label) = static::$methods[$method];

		$this->$action($args, $buffer_id, $label);

		$this->query_sql = null;

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
		array_push($args, $label);
		$this->buffers[$buffer_id][] = $args;
	}


	/**
	 * Process all buffers and build SQL query. Side product is array of 
	 * parameters (stored in $this->args) to bind with query.
	 */
	public function compile()
	{
		$this->sqlStart();

		echo 'SELECT NULL -- ('.__CLASS__.') --';

		return $this->sqlFinish();
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
		if ($this->query_sql === null) {
			$this->compile();
		}
		return $this->pdo->exec($this->query_sql);
	}


	/**
	 * Builds, binds and executes an SQL statement, returning a result set 
	 * as a PDOStatement object.
	 *
	 * Proxy to PDOStatement::prepare() & PDOStatement::bindValue() & PDOStatement::query().
	 * But if there is nothing to bind, PDO::query() is called instead.
	 */
	public function query()
	{
		if ($this->query_sql === null) {
			$this->compile();
		}

		if (empty($this->query_params)) {
			return $this->pdo->query($this->query_sql);
		} else {
			$stmt = $this->prepare();

			$i = 1;
			foreach ($this->query_params as $param) {
				if (is_bool($param)) {
					$stmt->bindValue($i, $param, \PDO::PARAM_BOOL);
				} else if (is_null($param)) {
					$stmt->bindValue($i, $param, \PDO::PARAM_NULL);
				} else if (is_int($param)) {
					$stmt->bindValue($i, $param, \PDO::PARAM_INT);
				} else {
					// ignore locales when convertiong to string
					$stmt->bindValue($i, strval($param), \PDO::PARAM_STR);
				}
				$i++;
			}

			$stmt->execute();
			return $stmt;
		}
	}


	/**
	 * Builds and prepares a statement for execution, returns a statement object.
	 *
	 * Proxy to PDO::prepare().
	 */
	public function prepare($driver_options = array())
	{
		if ($this->query_sql === null) {
			$this->compile();
		}

		return $this->pdo->prepare($this->query_sql, $driver_options);
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

		// Flatten parameters before bind
		if (!empty($this->query_params)) {
			$this->query_params = call_user_func_array('array_merge', $this->query_params);
		}
		return $this;
	}


	/**
	 * Add SQL with parameters. Parameters are stored in groups, merge to 
	 * one array is done at the end (using single array_merge call).
	 */
	protected function sqlBuffer($buf)
	{
		if (empty($buf)) {
			return;
		}
		
		$sql = array_shift($buf);

		if (is_array($sql)) {
			$first = true;
			foreach ($sql as $fragment) {
				if ($first) {
					$first = false;
				} else {
					echo ' ';
				}
				if ($fragment instanceof self) {
					$fragment->indent = $this->sub_indent."\t";
					$fragment->compile();
					echo "(\n", $fragment->query_sql, $this->sub_indent, ")";
					$this->query_params[] = $fragment->query_params;
				} else {
					echo $fragment;
				}
			}
		} else {
			echo $sql;
		}

		if (!empty($buf)) {
			$this->query_params[] = $buf;
		}
	}


	protected function sqlRawBuffer($buf)
	{
		if (is_array($buf[0])) {
			echo join("\n", $buf[0]);
		} else {
			echo $buf[0];
		}
	}


	protected function sqlComment($buffer_id)
	{
		if (isset($this->buffers[$buffer_id])) {
			foreach ($this->buffers[$buffer_id] as $buf) {
				echo $this->indent, '-- ', str_replace(array("\r", "\n"), array('', "\n".$this->indent.'-- '), $this->sqlRawBuffer($buf)), "\n";
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


	protected function sqlStatementFlags($buffer_id, $flag_buffer_ids, $decorations)
	{
		$first = false;

		if ($decorations & self::INDENT) {
			echo $this->indent;
			$first = true;
		}

		if ($decorations & self::LABEL) {
			if ($first) {
				$first = false;
			} else {
				echo ' ';
			}
			echo $buffer_id;
			$first = false;
		}

		foreach ($flag_buffer_ids as $flag_buf) {
			if (isset($this->buffers[$flag_buf])) {
				if ($first) {
					$first = false;
				} else {
					echo ' ';
				}
				echo $this->buffers[$flag_buf];
			}
		}

		if ($decorations & self::EOL) {
			echo "\n";
		}
	}


	protected function sqlList($buffer_id, $decorations)
	{
		$first = true;

		if (isset($this->buffers[$buffer_id])) {
			if ($decorations & self::INDENT) {
				if ($decorations & self::BRACKETS) {
					echo $this->sub_indent;
				} else {
					echo $this->indent;
				}
			} else if ($decorations & (self::LABEL | self::BRACKETS)) {
				echo ' ';
			}
			if ($decorations & self::LABEL) {
				echo $buffer_id;
			}
			if ($decorations & self::BRACKETS) {
				echo '(';
			}
			foreach ($this->buffers[$buffer_id] as $buf) {
				if ($decorations & self::BRACKETS) {
					if ($first) {
						$first = false;
					} else {
						echo ", ";
					}
				} else {
					if ($first) {
						$first = false;
						echo ' ';
					} else {
						echo ",\n", $this->sub_indent;
					}
				}
				$this->sqlBuffer($buf);
			}
			if ($decorations & self::BRACKETS) {
				echo ')';
			}
			if ($decorations & self::EOL) {
				echo "\n";
			}
		}
	}


	protected function sqlValuesList($buffer_id)
	{
		$first = true;

		if (isset($this->buffers[$buffer_id])) {
			echo $this->indent, $buffer_id, "\n";
			foreach ($this->buffers[$buffer_id] as $buf) {
				if (count($buf) == 1) {
					// One argument -- insert values from array
					foreach ($buf[0] as $row) {
						if ($first) {
							$first = false;
							echo $this->sub_indent, '(';
						} else {
							echo "),\n", $this->sub_indent, '(';
						}

						echo join(', ', array_map(array($this, 'quote'), $row)); // FIXME: bind values
					}
				} else {
					throw new \Exception('Not implemented yet.');
				}
			}
			echo ')';
			echo "\n";
		}
	}


	protected function sqlJoins($buffer_id)
	{
		$first = true;

		if (isset($this->buffers[$buffer_id])) {
			foreach ($this->buffers[$buffer_id] as $buf) {
				$join = array_pop($buf);
				echo $this->indent, $join, " ", $this->sqlBuffer($buf), "\n";
			}
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
				echo $this->sqlBuffer($buf), ")\n";
			}
		}
	}

}

