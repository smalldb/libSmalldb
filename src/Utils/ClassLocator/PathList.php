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

namespace Smalldb\StateMachine\Utils\ClassLocator;

class PathList
{
	private array $paths;
	protected string $separator = '/';

	public function __construct(array $paths = [])
	{
		foreach ($paths as $path) {
			$this->add($path);
		}
	}


	protected function normalize(string $path): string
	{
		return $path[-1] === $this->separator ? $path : $path . $this->separator;
	}


	public function add(string $path)
	{
		$p = $this->normalize($path);
		$this->paths[$p] = $p;
	}


	public function isEmpty(): bool
	{
		return empty($this->paths);
	}


	public function containsExact(string $path): bool
	{
		return isset($this->paths[$this->normalize($path)]);
	}


	/**
	 * $path is a subpath (subdirectory) of one of the paths in the list.
	 */
	public function contains(string $path): bool
	{
		$path = $this->normalize($path);

		if ($this->containsExact($path)) {
			return true;
		}

		// TODO: Optimize this.
		foreach ($this->paths as $p) {
			if ($this->startsWith($p, $path)) {
				return true;
			}
		}
		return false;
	}


	private function startsWith(string $prefix, string $haystack): bool
	{
		return strncmp($prefix, $haystack, strlen($prefix)) === 0;
	}

}
