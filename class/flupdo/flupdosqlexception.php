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

class FlupdoSqlException extends \Exception
{
	protected $error_info;
	protected $query_sql;
	protected $query_params;

	function __construct($error_info, $query_sql, $query_params)
	{
		$this->error_info = (array) $error_info;
		$this->query_sql = (string) $query_sql;
		$this->query_params = (array) $query_params;

		parent::__construct('SQL error '.join(': ', $this->error_info)."\n"
			."Query:\n".$query_sql);
	}


	function getQuerySql()
	{
		return $this->query_sql;
	}


	function getQueryParams()
	{
		return $this->query_params;
	}


	function getErrorInfo()
	{
		return $this->error_info;
	}

}

