<?php declare(strict_types = 1);
/*
 * Copyright (c) 2019, Josef Kufner  <josef@kufner.cz>
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

namespace Smalldb\StateMachine\SqlExtension\Definition;

use Smalldb\StateMachine\Definition\Builder\ExtensionPlaceholderInterface;
use Smalldb\StateMachine\Definition\ExtensionInterface;


class SqlPropertyExtensionPlaceholder implements ExtensionPlaceholderInterface
{
	public bool $isId = false;
	public ?string $sqlColumn = null;
	public ?string $sqlSelect = null;


	public function __construct()
	{
	}


	public function buildExtension(): ?ExtensionInterface
	{
		if ($this->sqlColumn !== null) {
			return new SqlPropertyExtension($this->sqlColumn, $this->isId);
		} else if ($this->sqlSelect !== null) {
			return new SqlCalculatedPropertyExtension($this->sqlSelect);
		} else {
			return null;  // @codeCoverageIgnore
		}
	}

}
