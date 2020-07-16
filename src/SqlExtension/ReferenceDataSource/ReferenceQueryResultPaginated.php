<?php declare(strict_types = 1);
/*
 * Copyright (c) 2020, Josef Kufner  <josef@kufner.cz>
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

namespace Smalldb\StateMachine\SqlExtension\ReferenceDataSource;

use Doctrine\DBAL\Driver\Statement;


class ReferenceQueryResultPaginated extends ReferenceQueryResult
{
	private int $resultCount;
	private int $currentPage;
	private int $pageSize;


	/**
	 * @param int $resultCount Total count of items matching the query
	 * @param int $currentPage Current page of the result; the first page is 1.
	 * @param int $pageSize    Number of items per page.
	 */
	public function __construct(?DataSource $originalDataSource, Statement $stmt, int $resultCount, int $currentPage, int $pageSize)
	{
		parent::__construct($originalDataSource, $stmt);
		$this->resultCount = $resultCount;
		$this->currentPage = $currentPage;
		$this->pageSize = $pageSize;
	}


	public function getResultCount(): int
	{
		return $this->resultCount;
	}


	public function getCurrentPage(): int
	{
		return $this->currentPage;
	}


	public function getPageSize(): int
	{
		return $this->pageSize;
	}


	public function getPageCount(): int
	{
		return (int) ceil($this->resultCount / $this->pageSize);
	}


	public function hasToPaginate(): bool
	{
		return $this->resultCount > $this->pageSize;
	}


	public function getFirstPage(): int
	{
		return 1;
	}


	public function getLastPage(): int
	{
		return (int) ceil($this->resultCount / $this->pageSize);
	}


	public function getPreviousPage(): int
	{
		return $this->currentPage > 1 ? $this->currentPage - 1 : 1;
	}


	public function getNextPage(): int
	{
		$lp = $this->getLastPage();
		return $this->currentPage < $lp ? $this->currentPage + 1 : $lp;
	}


	public function hasPreviousPage(): bool
	{
		return $this->currentPage > 1;
	}


	public function hasNextPage(): bool
	{
		return $this->currentPage < $this->getLastPage();
	}

}
