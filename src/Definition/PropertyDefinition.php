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

use JsonSerializable;


class PropertyDefinition extends ExtensibleDefinition implements JsonSerializable
{
	private string $name;
	private ?string $type;
	private ?bool $isNullable;


	/**
	 * PropertyDefinition constructor.
	 *
	 * @param string $name
	 * @param string|null $type
	 * @param bool|null $isNullable
	 * @param ExtensionInterface[] $extensions
	 * @internal
	 */
	public function __construct(string $name, ?string $type, ?bool $isNullable, array $extensions = [])
	{
		parent::__construct($extensions);
		$this->name = $name;
		$this->type = $type;
		$this->isNullable = $isNullable;
	}


	public function getName(): string
	{
		return $this->name;
	}


	public function getType(): ?string
	{
		return $this->type;
	}


	public function isNullable(): ?bool
	{
		return $this->isNullable;
	}


	public function jsonSerialize()
	{
		return array_merge(get_object_vars($this), parent::jsonSerialize());
	}

}
