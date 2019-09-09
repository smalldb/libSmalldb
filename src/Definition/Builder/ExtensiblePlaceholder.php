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


use Smalldb\StateMachine\Definition\ExtensionInterface;

/**
 * Class ExtensiblePlaceholder
 *
 * A placeholder for ExtensibleDefinition.
 */
abstract class ExtensiblePlaceholder
{

	/** @var ExtensionPlaceholderInterface[] */
	private $extensionPlaceholders = [];


	/**
	 * ExtensibleDefinition constructor.
	 * Note: $extensions is an array of objects indexed by their respective class names.
	 *
	 * @param ExtensionPlaceholderInterface[] $extensionPlaceholders
	 */
	public function __construct(array $extensionPlaceholders)
	{
		// Check for a plain list of extensions. We should always use a builder which checks everything.
		if (isset($extensionPlaceholders[0])) {
			throw new \InvalidArgumentException('The array of extensions must be indexed by their class names.');
		}
		$this->extensionPlaceholders = $extensionPlaceholders;
	}


	public function hasExtensionPlaceholder(string $extensionPlaceholderClassName): bool
	{
		return isset($this->extensionPlaceholders[$extensionPlaceholderClassName]);
	}


	public function getExtensionPlaceholder(string $extensionPlaceholderClassName): ExtensionPlaceholderInterface
	{
		$ext = $this->extensionPlaceholders[$extensionPlaceholderClassName] ?? null;

		if ($ext === null) {
			return ($this->extensionPlaceholders[$extensionPlaceholderClassName] = new $extensionPlaceholderClassName());
		} else if ($ext instanceof $extensionPlaceholderClassName) {
			return $ext;
		} else {
			throw new InvalidExtensionPlaceholderException("Unexpected extension placeholder type: $extensionPlaceholderClassName"
				. " should not be an instance of " . get_class($ext));
		}
	}


	/**
	 * @return ExtensionInterface[]
	 */
	protected function buildExtensions(): array
	{
		$extensions = [];
		foreach ($this->extensionPlaceholders as $placeholder) {
			$extension = $placeholder->buildExtension();
			$extensions[get_class($extension)] = $extension;
		}
		return $extensions;
	}

}
