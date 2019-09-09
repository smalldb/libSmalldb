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

use Smalldb\StateMachine\Definition\ActionDefinition;


/**
 * Class ActionPlaceholder
 *
 * @internal
 */
class ActionPlaceholder extends ExtensiblePlaceholder
{
	/** @var string */
	public $name;

	/** @var string|null */
	public $color;

	public function __construct(string $name, ?string $color = null, array $extensionPlaceholders = [])
	{
		parent::__construct($extensionPlaceholders);
		$this->name = $name;
	}


	public function buildActionDefinition(array $transitions): ActionDefinition
	{
		return new ActionDefinition($this->name, $transitions, $this->color, $this->buildExtensions());
	}

}
