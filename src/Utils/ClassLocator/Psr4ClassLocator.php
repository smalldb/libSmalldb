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

use Smalldb\StateMachine\InvalidArgumentException;


class Psr4ClassLocator implements ClassLocator
{

	/** @var string */
	private $namespace;

	/** @var string */
	private $directory;


	public function __construct(string $namespace, string $directory)
	{
		$this->namespace = $namespace === '' ? '\\' : ($namespace[-1] === "\\" ? $namespace : $namespace . "\\");
		$this->directory = $directory;

		if (!is_dir($this->directory)) {
			throw new InvalidArgumentException("Not a directory: $this->directory");
		}
	}


	public function getClasses(): iterable
	{
		yield from $this->scanDirectory($this->namespace, $this->directory);
	}


	private function scanDirectory($namespace, $directory): iterable
	{
		foreach (scandir($directory) as $f) {
			if ($f[0] === '.') {
				continue;
			}
			$fullPath = "$directory/$f";
			$dotPos = strpos($f, '.');
			$className = $namespace . ($dotPos ? substr($f, 0, $dotPos) : $f);
			if (is_dir($fullPath)) {
				yield from $this->scanDirectory($className . "\\", $fullPath);
			} else if ($dotPos && substr($f, $dotPos) === '.php') {
				if (class_exists($className) || interface_exists($className)) {
					yield $className;
				}
			}
		}
	}

}
