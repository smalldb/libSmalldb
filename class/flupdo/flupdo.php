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

/**
 * Extend PDO class with query builder starting methods. These methods are 
 * simple factory & proxy to FlupdoBuilder.
 */
class Flupdo extends \PDO
{
	/**
	 * Returns fresh instance of Flupdo query builder.
	 */
	function createFlupdoBuilder(& $clause_tree)
	{
		return new FlupdoBuilder($this, $clause_tree);
	}


	/**
	 * Mark each node in clause tree with per-tree unique ID. This ID is 
	 * used to identify correct buffer in FlupdoBuilder.
	 */
	private function generateTreeIds(& $node, $id = '/')
	{
		$node['#'] = $id;

		foreach ($node as $k => & $child) {
			if (is_string($k) && is_array($child)) {
				$this->generateTreeIds($node[$k], $id.$k.'/');
			}
		}
	}

	function from(/* ... */)
	{
		$args = func_get_args();
		return $this->createFlupdoBuilder('SELECT')->__call('from', $args);
	}

	function select(/* ... */)
	{
		$args = func_get_args();

		static $clause_tree = array(
			/* -- http://dev.mysql.com/doc/refman/5.5/en/select.html
			 * SELECT
			 *   [ALL | DISTINCT | DISTINCTROW ]
			 *     [HIGH_PRIORITY]
			 *     [STRAIGHT_JOIN]
			 *     [SQL_SMALL_RESULT] [SQL_BIG_RESULT] [SQL_BUFFER_RESULT]
			 *     [SQL_CACHE | SQL_NO_CACHE] [SQL_CALC_FOUND_ROWS]
			 *   select_expr [, select_expr ...]
			 *   [FROM table_references
			 *   [WHERE where_condition]
			 *   [GROUP BY {col_name | expr | position}
			 *     [ASC | DESC], ... [WITH ROLLUP]]
			 *   [HAVING where_condition]
			 *   [ORDER BY {col_name | expr | position}
			 *     [ASC | DESC], ...]
			 *   [LIMIT {[offset,] row_count | row_count OFFSET offset}]
			 *   [PROCEDURE procedure_name(argument_list)]
			 *   [INTO OUTFILE 'file_name'
			 *       [CHARACTER SET charset_name]
			 *       export_options
			 *     | INTO DUMPFILE 'file_name'
			 *     | INTO var_name [, var_name]]
			 *     [FOR UPDATE | LOCK IN SHARE MODE]]
			 */
			null, null,
			array(
				'--HEADER'		=> 'comment',
				'SELECT'		=> 'list',
				'DISTINCT'		=> 'flag',
				'HIGH_PRIORITY'		=> 'flag',
				'STRAIGHT_JOIN'		=> 'flag',
				'SQL_SMALL_RESULT'	=> 'flag',
				'SQL_BIG_RESULT'	=> 'flag',
				'SQL_BUFFER_RESULT'	=> 'flag',
				'SQL_CACHE'		=> 'flag',
				'SQL_CALC_FOUND_ROWS'	=> 'flag',
				'FROM'			=> 'list',
				'WHERE'			=> 'conditions',
				'GROUP BY'		=> 'list',
				'HAVING'		=> 'conditions',
				'ORDER BY'		=> 'list',
				'LIMIT'			=> 'offset',
				'PROCEDURE'		=> 'sql',
				'INTO'			=> 'string',
				'LOCK'			=> 'flag',
				'--FOOTER'		=> 'comment',
			),

			// 'method' => array('realMethodToCall', 'extra arguments - string or array',
			// 	array(/* buffers */),
			// 	'&' => 'merge with that one',
			// 	/* recursive */
			// ),

			'headerComment' => array(
				'--HEADER', null,
			),

			'select' => array(
				'SELECT', null,
				array(
					'string',
					'AS' => 'alias',
				),
				'as' => array('AS', null),
			),

			// Flags
			'all'			=> array('DISTINCT',		'ALL'),
			'distinct'		=> array('DISTINCT',		'DISTINCT'),
			'distinctRow'		=> array('DISTINCT',		'DISTINCTROW'),
			'highPriority'		=> array('HIGH_PRIORITY',	'HIGH_PRIORITY'),
			'straightJoin'		=> array('STRAIGHT_JOIN',	'STRAIGHT_JOIN'),
			'sqlSmallResult'	=> array('SQL_SMALL_RESULT',	'SQL_SMALL_RESULT'),
			'sqlBigResult'		=> array('SQL_BIG_RESULT',	'SQL_BIG_RESULT'),
			'sqlBufferResult'	=> array('SQL_BUFFER_RESULT',	'SQL_BUFFER_RESULT'),
			'sqlCache'		=> array('SQL_CACHE',		'SQL_CACHE'),
			'sqlNoCache'		=> array('SQL_CACHE',		'SQL_NO_CACHE'),
			'sqlCalcFoundRows'	=> array('SQL_CALC_FOUND_ROWS',	'SQL_CALC_FOUND_ROWS'),

			// From and joins
			'from' => array(
				'FROM', null,
				array(
					'ident',
					'AS' => 'alias',
					'JOIN' => 'join',
				),
				'join' => array('JOIN', 'JOIN',
					array(
						'ident',
						'AS' => 'alias',
						'USING' => 'ident',
						'ON' => 'conditions',
					),
					'as' => array('AS', null),
					'using' => array('USING', null),
					'on' => array('ON', null),
				),
				'innerJoin'		=> array('JOIN', 'INNER JOIN',			'&' => 'join'),
				'crossJoin'		=> array('JOIN', 'CROSS JOIN',			'&' => 'join'),
				'straightJoin'		=> array('JOIN', 'STRAIGHT_JOIN',		'&' => 'join'),
				'leftJoin'		=> array('JOIN', 'LEFT JOIN',			'&' => 'join'),
				'rightJoin'		=> array('JOIN', 'RIGHT JOIN',			'&' => 'join'),
				'leftOuterJoin'		=> array('JOIN', 'LEFT OUTER JOIN',		'&' => 'join'),
				'rightOuterJoin'	=> array('JOIN', 'RIGHT OUTER JOIN',		'&' => 'join'),
				'naturalLeftJoin'	=> array('JOIN', 'NATURAL LEFT JOIN',		'&' => 'join'),
				'naturalRightJoin'	=> array('JOIN', 'NATURAL RIGHT JOIN',		'&' => 'join'),
				'naturalLeftOuterJoin'	=> array('JOIN', 'NATURAL LEFT OUTER JOIN',	'&' => 'join'),
				'naturalRightOuterJoin'	=> array('JOIN', 'NATURAL RIGHT OUTER JOIN',	'&' => 'join'),
			),

			'where' => array('WHERE', null),
			'groupBy' => array('GROUP BY', null,
				array(
					'sql',
					'ASC' => 'flag',
				),
				'asc' => array('ASC', 'ASC'),
				'desc' => array('ASC', 'DESC'),
			),
			'having' => array('HAVING', null),
			'orderBy' => array('ORDER BY', null,
				array(
					'sql',
					'ASC' => 'flag',
				),
				'asc' => array('ASC', 'ASC'),
				'desc' => array('ASC', 'DESC'),
			),
			'limit' => array('LIMIT', null,
				array(
					'value',
					'OFFSET' => 'offset',
				),
				'offset' => array('OFFSET', null),
			),
			'procedure' => array('PROCEDURE', null),
			'intoOutfile' => array('INTO', null,
				array(
					'string',
					'CHARACTER SET' => 'string',
				),
			),
			'intoDumpfile' => array('INTO', null),
			'into' => array('INTO', null, 'sql', null ),
			'forUpdate' => array('LOCK', 'FOR UPDATE'),
			'lockInShareMode' => array('LOCK', 'LOCK IN SHARE MODE'),
		);

		if (!isset($clause_tree['#'])) {
			print_r($clause_tree);
			echo "\n\n----------------------\n\n\n";
			$this->generateTreeIds($clause_tree);
			print_r($clause_tree);
		}

		return $this->createFlupdoBuilder($clause_tree)->__call('select', $args);
	}


	function insert(/* ... */)
	{
		$args = func_get_args();
		return $this->createFlupdoBuilder('INSERT')->__call('insert', $args);
	}


	function update(/* ... */)
	{
		$args = func_get_args();
		return $this->createFlupdoBuilder('UPDATE')->__call('update', $args);
	}


	function delete(/* ... */)
	{
		$args = func_get_args();
		return $this->createFlupdoBuilder('DELETE')->__call('delete', $args);
	}

}

