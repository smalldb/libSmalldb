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

