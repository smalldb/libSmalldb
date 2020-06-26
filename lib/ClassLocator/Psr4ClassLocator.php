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

namespace Smalldb\ClassLocator;

use Smalldb\StateMachine\InvalidArgumentException;


class Psr4ClassLocator implements ClassLocator
{

	private string $namespace;
	private string $directory;
	private RealPathList $includePaths;
	private PathList $excludePaths;


	public function __construct(string $namespace, string $directory, $includePaths = [], $excludePaths = [])
	{
		$this->namespace = $namespace === '' ? '\\' : ($namespace[-1] === "\\" ? $namespace : $namespace . "\\");
		$this->directory = $directory;

		if (!is_dir($this->directory)) {
			throw new InvalidArgumentException("Not a directory: $this->directory");
		}

		if ($includePaths instanceof RealPathList) {
			$this->includePaths = $includePaths;
		} else {
			$this->includePaths = new RealPathList($directory, $includePaths ?? []);
		}

		if ($excludePaths instanceof RealPathList) {
			$this->excludePaths = $excludePaths;
		} else {
			$this->excludePaths = new RealPathList($directory, $excludePaths ?? []);
		}
	}


	public function getClasses(): \Generator
	{
		yield from $this->scanDirectory($this->namespace, $this->directory);
	}


	private function scanDirectory($namespace, $absPath): \Generator
	{
		foreach (scandir($absPath) as $f) {
			if ($f[0] === '.') {
				continue;
			}

			$fileAbsPath = "$absPath/$f";
			if ($this->excludePaths->containsExact($fileAbsPath)) {
				continue;
			}

			$dotPos = strpos($f, '.');
			$className = $namespace . ($dotPos ? substr($f, 0, $dotPos) : $f);
			if (is_dir($fileAbsPath)) {
				yield from $this->scanDirectory($className . "\\", $fileAbsPath);
			} else if ($dotPos && substr($f, $dotPos) === '.php') {
				if (!$this->includePaths->isEmpty() && !$this->includePaths->contains(dirname($fileAbsPath))) {
					continue;
				}
				try {
					if (class_exists($className) || interface_exists($className, false) || trait_exists($className, false)) {
						yield $fileAbsPath => $className;
					}
				}
				catch (\Throwable $ex) {
					// Ignore errors; just don't enumerate the broken class.
				}
			}
		}
	}


	public function mapClassNameToFileName(string $className): ?string
	{
		if (str_starts_with($className, $this->namespace)) {
			$relPath = substr($className, strlen($this->namespace));
			if (!preg_match('/^[A-Za-z0-9_\\\\]+$/', $relPath)) {
				throw new InvalidArgumentException("Invalid class name: " . $className);
			}
			return $this->directory . DIRECTORY_SEPARATOR
				. str_replace('\\', DIRECTORY_SEPARATOR, $relPath) . '.php';
		} else {
			return null;
		}
	}


	public function mapFileNameToClassName(string $fileName): ?string
	{
		if (str_starts_with($fileName, $this->directory)) {
			$relPath = substr($fileName, strlen($this->directory));
			if (!preg_match('/^[A-Za-z0-9_\\/\\\\]+\\.php$/', $relPath)) {
				throw new InvalidArgumentException("Invalid filename: " . $fileName);
			}
			return $this->namespace . trim(str_replace(['.php', '\\', '/'], ['', '\\', '\\'], $relPath), '\\');
		} else {
			return null;
		}
	}

}
