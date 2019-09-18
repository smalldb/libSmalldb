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

namespace Smalldb\StateMachine\Definition;


class StateDefinition extends ExtensibleDefinition
{
	/** @var string */
	private $name;

	/** @var ?string */
	private $color;


	/**
	 * StateDefinition constructor.
	 *
	 * @param string $name
	 * @param string|null $color
	 * @param ExtensionInterface[] $extensions
	 * @internal
	 */
	public function __construct(string $name, ?string $color = null, array $extensions = [])
	{
		parent::__construct($extensions);
		$this->name = $name;
		$this->color = $color;
	}


	public function getName(): string
	{
		return $this->name;
	}


	public function getColor(): ?string
	{
		return $this->color;
	}


	public function jsonSerialize()
	{
		return array_merge(get_object_vars($this), parent::jsonSerialize());
	}

}