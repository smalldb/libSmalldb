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

use Smalldb\StateMachine\Definition\StateDefinition;


/**
 * Class StatePlaceholder
 *
 * @internal
 */
class StatePlaceholder extends ExtensiblePlaceholder
{
	public string $name;
	public ?string $color;


	public function __construct(string $name, ?string $color = null, array $extensionPlaceholders = [])
	{
		parent::__construct($extensionPlaceholders);
		$this->name = $name;
		$this->color = $color;
	}


	public function buildStateDefinition(): StateDefinition
	{
		return new StateDefinition($this->name, $this->color, $this->buildExtensions());
	}

}
