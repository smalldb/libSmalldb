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


abstract class ExtensibleDefinition implements JsonSerializable
{

	/** @var ExtensionInterface[] */
	private array $extensions = [];


	/**
	 * ExtensibleDefinition constructor.
	 * Note: $extensions is an array of objects indexed by their respective class names.
	 *
	 * @param ExtensionInterface[] $extensions
	 */
	public function __construct(array $extensions)
	{
		// Check for a plain list of extensions. We should always use a builder which checks everything.
		if (isset($extensions[0])) {
			throw new \InvalidArgumentException('The array of extensions must be indexed by their class names.');
		}
		$this->extensions = $extensions;
	}


	/**
	 * Return true if an extension of given type is defined for this element.
	 */
	public function hasExtension(string $extensionClassName): bool
	{
		return isset($this->extensions[$extensionClassName]);
	}


	/**
	 * Get an extension of given type. Throws UndefinedExtensionException if the extension is not found.
	 *
	 * It would be nice to write getExtension<T>(): T ...
	 *
	 * @template ExtensionInterface
	 * @param class-string<ExtensionInterface> $extensionClassName
	 * @return ExtensionInterface
	 */
	public function getExtension(string $extensionClassName): ExtensionInterface
	{
		$ext = $this->extensions[$extensionClassName] ?? null;

		if ($ext === null) {
			throw new UndefinedExtensionException("Extension not defined: $extensionClassName");
		} else if ($ext instanceof $extensionClassName) {
			return $ext;
		} else {
			throw new InvalidExtensionException("Unexpected extension type: $extensionClassName"
				. " should not be an instance of " . get_class($ext));
		}
	}


	/**
	 * Get an extension of given type. Just like getExtension(), but returns null if the extension is not found.
	 *
	 * It would be nice to write getExtension<T>(): T ...
	 *
	 * @template ExtensionInterface
	 * @param class-string<ExtensionInterface> $extensionClassName
	 * @return ExtensionInterface
	 */
	public function findExtension(string $extensionClassName): ?ExtensionInterface
	{
		$ext = $this->extensions[$extensionClassName] ?? null;

		if ($ext === null) {
			return null;
		} else if ($ext instanceof $extensionClassName) {
			return $ext;
		} else {
			throw new InvalidExtensionException("Unexpected extension type: $extensionClassName"
				. " should not be an instance of " . get_class($ext));
		}
	}


	/**
	 * Get a list of existing extensions
	 *
	 * @return string[]
	 */
	public function getExtensionClassNames(): array
	{
		return array_keys($this->extensions);
	}


	public function jsonSerialize()
	{
		return [
			'extensions' => $this->extensions ?: (object) [],
		];
	}

}
