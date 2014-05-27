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
 * Flupdo Builder for REPLACE statement
 *
 * -- http://dev.mysql.com/doc/refman/5.5/en/replace.html
 *
 * REPLACE [LOW_PRIORITY | DELAYED]
 *  [INTO] tbl_name [(col_name,...)]
 *  {VALUES | VALUE} ({expr | DEFAULT},...),(...),...
 *
 * -- OR --
 *
 * REPLACE [LOW_PRIORITY | DELAYED]
 *  [INTO] tbl_name
 *  SET col_name={expr | DEFAULT}, ...
 *
 * -- OR --
 *
 * REPLACE [LOW_PRIORITY | DELAYED]
 *  [INTO] tbl_name [(col_name,...)]
 *  SELECT ...
 *
 */

class ReplaceBuilder extends FlupdoBuilder
{

	/**
	 * @copydoc FlupdoBuilder\$methods
	 */
	protected static $methods = array(
		// Header
		'headerComment'		=> array('replace',	'-- HEADER'),
		'replace'		=> array('add',		'REPLACE'),
		'into'			=> array('replace',	'INTO'),

		// Flags
		'lowPriority'		=> array('setFlag',	'PRIORITY',		'LOW_PRIORITY'),
		'delayed'		=> array('setFlag',	'PRIORITY',		'DELAYED'),

		// Conditions & Values
		'values'		=> array('add',		'VALUES'),
		'set'			=> array('add',		'SET'),
		'where'			=> array('add',		'WHERE'),

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
		$this->sqlStatementFlags('REPLACE', array(
				'PRIORITY',
			), self::INDENT | self::LABEL);
		$this->sqlList('INTO', self::LABEL | self::EOL);
		$this->sqlList('REPLACE', self::INDENT | self::BRACKETS | self::EOL);

		$this->sqlValuesList('VALUES');
		$this->sqlList('SET', self::INDENT | self::LABEL | self::EOL);
		$this->sqlConditions('WHERE');

		$this->sqlComment('-- FOOTER');

		return $this->sqlFinish();
	}

}

