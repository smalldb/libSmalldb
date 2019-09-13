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

namespace Smalldb\StateMachine\Definition\Builder;

use Smalldb\StateMachine\Definition\PropertyDefinition;


/**
 * Class PropertyPlaceholder
 *
 * @internal
 */
class PropertyPlaceholder extends ExtensiblePlaceholder
{
	/** @var string */
	public $name;

	/** @var string|null */
	public $type;

	/** @var bool|null */
	public $isNullable;


	public function __construct(string $name, ?string $type = null, ?bool $isNullable = null, array $extensionPlaceholders = [])
	{
		parent::__construct($extensionPlaceholders);
		$this->name = $name;
		$this->type = $type;
		$this->isNullable = $isNullable;
	}


	public function buildPropertyDefinition(): PropertyDefinition
	{
		return new PropertyDefinition($this->name, $this->type, $this->isNullable, $this->buildExtensions());
	}

}
