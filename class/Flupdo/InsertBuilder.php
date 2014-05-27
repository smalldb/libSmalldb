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
 * Flupdo Builder for INSERT statement
 *
 * -- http://dev.mysql.com/doc/refman/5.5/en/insert.html
 *
 * INSERT [LOW_PRIORITY | DELAYED | HIGH_PRIORITY] [IGNORE]
 *  [INTO] tbl_name [(col_name,...)]
 *  {VALUES | VALUE} ({expr | DEFAULT},...),(...),...
 *  [ ON DUPLICATE KEY UPDATE
 *    col_name=expr
 *      [, col_name=expr] ... ]
 *
 * -- OR --
 *
 *  INSERT [LOW_PRIORITY | DELAYED | HIGH_PRIORITY] [IGNORE]
 *  [INTO] tbl_name
 *  SET col_name={expr | DEFAULT}, ...
 *  [ ON DUPLICATE KEY UPDATE
 *    col_name=expr
 *     [, col_name=expr] ... ]
 *
 * -- OR --
 *
 * INSERT [LOW_PRIORITY | HIGH_PRIORITY] [IGNORE]
 *  [INTO] tbl_name [(col_name,...)]
 *  SELECT ...
 *  [ ON DUPLICATE KEY UPDATE
 *    col_name=expr
 *      [, col_name=expr] ... ]
 */

class InsertBuilder extends FlupdoBuilder
{

	/**
	 * @copydoc FlupdoBuilder\$methods
	 */
	protected static $methods = array(
		// Header
		'headerComment'		=> array('replace',	'-- HEADER'),
		'insert'		=> array('add',		'INSERT'),
		'into'			=> array('replace',	'INTO'),

		// Flags
		'lowPriority'		=> array('setFlag',	'PRIORITY',		'LOW_PRIORITY'),
		'delayed'		=> array('setFlag',	'PRIORITY',		'DELAYED'),
		'highPriority'		=> array('setFlag',	'PRIORITY',		'HIGH_PRIORITY'),
		'ignore'		=> array('setFlag',	'IGNORE',		'IGNORE'),

		// Conditions & Values
		'values'		=> array('add',		'VALUES'),
		'set'			=> array('add',		'SET'),
		'where'			=> array('add',		'WHERE'),
		'onDuplicateKeyUpdate'	=> array('add',		'ON DUPLICATE KEY UPDATE'),

		// Footer
		'footerComment'		=> array('replace',	'-- FOOTER'),
	);


	/**
	 * @copydoc FlupdoBuilder\compile()
	 */
	public function compile()
	{
		$this->sqlStart();

		$this->sqlComment('-- HEADER');
		$this->sqlStatementFlags('INSERT', array(
				'PRIORITY',
				'IGNORE'
			), self::INDENT | self::LABEL);
		$this->sqlList('INTO', self::LABEL | self::EOL);
		$this->sqlList('INSERT', self::INDENT | self::BRACKETS | self::EOL);

		$this->sqlValuesList('VALUES');
		$this->sqlList('SET', self::INDENT | self::LABEL | self::EOL);
		$this->sqlConditions('WHERE');
		$this->sqlList('ON DUPLICATE KEY UPDATE', self::INDENT | self::LABEL | self::EOL);

		$this->sqlComment('-- FOOTER');

		return $this->sqlFinish();
	}

}

