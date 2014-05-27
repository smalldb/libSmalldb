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
 * Something is wrong in SQL query.
 */
class FlupdoSqlException extends \Exception
{
	/**
	 * Error info from database.
	 */
	protected $error_info;

	/**
	 * Failed SQL query.
	 */
	protected $query_sql;

	/**
	 * Parameters bounded to SQL query.
	 */
	protected $query_params;


	/**
	 * Constructor.
	 */
	function __construct($error_info, $query_sql, $query_params)
	{
		$this->error_info = (array) $error_info;
		$this->query_sql = (string) $query_sql;
		$this->query_params = (array) $query_params;

		parent::__construct('SQL error '.join(': ', $this->error_info)."\n"
			."Query:\n".$query_sql);
	}


	/**
	 * Get failed SQL query.
	 */
	function getQuerySql()
	{
		return $this->query_sql;
	}


	/**
	 * Get parameters bounded to failed SQL query.
	 */
	function getQueryParams()
	{
		return $this->query_params;
	}


	/**
	 * Get error info received from database.
	 */
	function getErrorInfo()
	{
		return $this->error_info;
	}

}

