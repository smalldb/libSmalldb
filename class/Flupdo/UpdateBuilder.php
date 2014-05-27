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
 * Flupdo Builder for UPDATE statement
 *
 * -- http://dev.mysql.com/doc/refman/5.5/en/update.html
 *
 * UPDATE [LOW_PRIORITY] [IGNORE] table_reference
 *  SET col_name1={expr1|DEFAULT} [, col_name2={expr2|DEFAULT}] ...
 *  [WHERE where_condition]
 *  [ORDER BY ...]
 *  [LIMIT row_count]
 *
 * -- OR --
 *
 * UPDATE [LOW_PRIORITY] [IGNORE] table_references
 *  SET col_name1={expr1|DEFAULT} [, col_name2={expr2|DEFAULT}] ...
 *  [WHERE where_condition]
 *
 */

class UpdateBuilder extends FlupdoBuilder
{

	/**
	 * @copydoc FlupdoBuilder\$methods
	 */
	protected static $methods = array(
		// Header
		'headerComment'		=> array('replace',	'-- HEADER'),
		'update'		=> array('add',		'UPDATE'),

		// Flags
		'lowPriority'		=> array('setFlag',	'PRIORITY',		'LOW_PRIORITY'),
		'ignore'		=> array('setFlag',	'IGNORE',		'IGNORE'),

		// Conditions & Values
		'set'			=> array('add',		'SET'),
		'where'			=> array('add',		'WHERE'),
		'orderBy'		=> array('add',		'ORDER BY'),
		'limit'			=> array('replace',	'LIMIT'),

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
		$this->sqlStatementFlags('UPDATE', array(
				'PRIORITY',
				'IGNORE'
			), self::INDENT | self::LABEL);
		$this->sqlList('UPDATE', self::EOL);

		$this->sqlList('SET', self::INDENT | self::LABEL | self::EOL);
		$this->sqlConditions('WHERE', self::INDENT | self::LABEL | self::EOL);
		$this->sqlList('ORDER BY', self::INDENT | self::LABEL | self::EOL);
		$this->sqlList('LIMIT', self::INDENT | self::LABEL | self::EOL);

		$this->sqlComment('-- FOOTER');

		return $this->sqlFinish();
	}

}

