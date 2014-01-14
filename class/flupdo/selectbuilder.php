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

namespace Smalldb\Flupdo;

/**
 * Flupdo Builder for SELECT statement
 *
 * -- http://dev.mysql.com/doc/refman/5.5/en/select.html
 *
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
class SelectBuilder extends FlupdoBuilder
{

	protected static $methods = array(
		//
		'headerComment'		=> array('replace',	'-- HEADER'),
		'select'		=> array('add',		'SELECT'),

		// Flags
		'all'			=> array('setFlag',	'DISTINCT',		'ALL'),
		'distinct'		=> array('setFlag',	'DISTINCT',		'DISTINCT'),
		'distinctRow'		=> array('setFlag',	'DISTINCT',		'DISTINCTROW'),
		'highPriority'		=> array('setFlag',	'HIGH_PRIORITY',	'HIGH_PRIORITY'),
		'straightJoin'		=> array('setFlag',	'STRAIGHT_JOIN',	'STRAIGHT_JOIN'),
		'sqlSmallResult'	=> array('setFlag',	'SQL_SMALL_RESULT',	'SQL_SMALL_RESULT'),
		'sqlBigResult'		=> array('setFlag',	'SQL_BIG_RESULT',	'SQL_BIG_RESULT'),
		'sqlBufferResult'	=> array('setFlag',	'SQL_BUFFER_RESULT',	'SQL_BUFFER_RESULT'),
		'sqlCache'		=> array('setFlag',	'SQL_CACHE',		'SQL_CACHE'),
		'sqlNoCache'		=> array('setFlag',	'SQL_CACHE',		'SQL_NO_CACHE'),
		'sqlCalcFoundRows'	=> array('setFlag',	'SQL_CALC_FOUND_ROWS',	'SQL_CALC_FOUND_ROWS'),

		// From and joins
		// FIXME: Joins should be part of FROM
		'from'			=> array('replace',	'FROM'),
		'join'			=> array('addJoin',	'JOIN',			'JOIN'),
		'innerJoin'		=> array('addJoin',	'JOIN',			'INNER JOIN'),
		'crossJoin'		=> array('addJoin',	'JOIN',			'CROSS JOIN'),
		'straightJoin'		=> array('addJoin',	'JOIN',			'STRAIGHT_JOIN'),
		'leftJoin'		=> array('addJoin',	'JOIN',			'LEFT JOIN'),
		'rightJoin'		=> array('addJoin',	'JOIN',			'RIGHT JOIN'),
		'leftOuterJoin'		=> array('addJoin',	'JOIN',			'LEFT OUTER JOIN'),
		'rightOuterJoin'	=> array('addJoin',	'JOIN',			'RIGHT OUTER JOIN'),
		'naturalLeftJoin'	=> array('addJoin',	'JOIN',			'NATURAL LEFT JOIN'),
		'naturalRightJoin'	=> array('addJoin',	'JOIN',			'NATURAL RIGHT JOIN'),
		'naturalLeftOuterJoin'	=> array('addJoin',	'JOIN',			'NATURAL LEFT OUTER JOIN'),
		'naturalRightOuterJoin'	=> array('addJoin',	'JOIN',			'NATURAL RIGHT OUTER JOIN'),

		// Conditions
		'where'			=> array('add',		'WHERE'),
		'groupBy'		=> array('add',		'GROUP BY'),
		'having'		=> array('add',		'HAVING'),
		'orderBy'		=> array('add',		'ORDER BY'),
		'limit'			=> array('replace',	'LIMIT'),
		'offset'		=> array('replace',	'OFFSET'),
		'procedure'		=> array('replace',	'PROCEDURE'),
		'into'			=> array('replace',	'INTO'),
		'forUpdate'		=> array('setFlag',	'LOCK',			'FOR UPDATE'),
		'lockInShareMode'	=> array('setFlag',	'LOCK',			'LOCK IN SHARE MODE'),

		'footerComment'		=> array('replace',	'-- FOOTER'),
	);


	public function compile()
	{
		if (empty($this->buffers['SELECT'])) {
			$this->select('*');
		}

		$this->sqlStart();

		$this->sqlComment('-- HEADER');
		$this->sqlStatementFlags('SELECT', array(
				'DISTINCT',
				'HIGH_PRIORITY',
				'STRAIGHT_JOIN',
				'SQL_SMALL_RESULT',
				'SQL_BIG_RESULT',
				'SQL_BUFFER_RESULT',
				'SQL_CACHE',
				'SQL_CALC_FOUND_ROWS'
			), self::INDENT | self::LABEL);
		$this->sqlList('SELECT', self::EOL);
		$this->sqlList('FROM', self::INDENT | self::LABEL | self::EOL);
		$this->sqlJoins('JOIN');
		$this->sqlConditions('WHERE');
		$this->sqlList('GROUP BY', self::INDENT | self::LABEL | self::EOL);
		$this->sqlConditions('HAVING');
		$this->sqlList('ORDER BY', self::INDENT | self::LABEL | self::EOL);
		if (isset($this->buffers['LIMIT'])) {
			$this->sqlList('LIMIT', self::INDENT | self::LABEL | self::EOL);
			$this->sqlList('OFFSET', self::INDENT | self::LABEL | self::EOL);
		}
		$this->sqlList('PROCEDURE', self::INDENT | self::LABEL | self::EOL);
		$this->sqlList('INTO', self::INDENT | self::LABEL | self::EOL);
		$this->sqlFlag('LOCK');
		$this->sqlComment('-- FOOTER');

		return $this->sqlFinish();
	}

}

