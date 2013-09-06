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


	public function compile()
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

