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
 * Flupdo Builder for DELETE statement
 *
 * -- http://dev.mysql.com/doc/refman/5.5/en/delete.html
 *
 *  DELETE [LOW_PRIORITY] [QUICK] [IGNORE] FROM tbl_name
 *  [WHERE where_condition]
 *  [ORDER BY ...]
 *  [LIMIT row_count]
 *
 * -- OR --
 *
 *  DELETE [LOW_PRIORITY] [QUICK] [IGNORE]
 *  tbl_name[.*] [, tbl_name[.*]] ...
 *  FROM table_references
 *  [WHERE where_condition]
 *
 * -- OR --
 *
 *  DELETE [LOW_PRIORITY] [QUICK] [IGNORE]
 *  FROM tbl_name[.*] [, tbl_name[.*]] ...
 *  USING table_references
 *  [WHERE where_condition]
 *
 */
class DeleteBuilder extends FlupdoBuilder
{

	/**
	 * Magic methods mapped to SQL fragments.
	 */
	protected static $methods = array(
		// Header
		'headerComment'		=> array('replace',	'-- HEADER'),
		'delete'		=> array('add',		'DELETE'),
		'from'			=> array('add',		'FROM'),

		// Flags
		'lowPriority'		=> array('setFlag',	'PRIORITY',		'LOW_PRIORITY'),
		'quick'			=> array('setFlag',	'QUICK',		'QUICK'),
		'ignore'		=> array('setFlag',	'IGNORE',		'IGNORE'),

		// Conditions & Values
		'using'			=> array('add',		'USING'),
		'where'			=> array('add',		'WHERE'),
		'orderBy'		=> array('add',		'ORDER BY'),
		'limit'			=> array('replace',	'LIMIT'),

		// Footer
		'footerComment'		=> array('replace',	'-- FOOTER'),
	);


	/**
	 * @copydoc FlupdoBuilder\compileQuery()
	 */
	protected function compileQuery()
	{
		$this->sqlStart();

		$this->sqlComment('-- HEADER');
		$this->sqlStatementFlags('DELETE', array(
				'PRIORITY',
				'QUICK',
				'IGNORE'
			), self::INDENT | self::LABEL);
		$this->sqlList('DELETE', 0);
		$this->sqlList('FROM', self::LABEL | self::EOL);
		$this->sqlConditions('USING', self::INDENT | self::LABEL | self::EOL);
		$this->sqlConditions('WHERE', self::INDENT | self::LABEL | self::EOL);
		$this->sqlList('ORDER BY', self::INDENT | self::LABEL | self::EOL);
		$this->sqlList('LIMIT', self::INDENT | self::LABEL | self::EOL);

		$this->sqlComment('-- FOOTER');

		return $this->sqlFinish();
	}

}

