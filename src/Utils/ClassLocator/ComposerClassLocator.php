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

use Composer\Autoload\ClassLoader;


class ComposerClassLocator implements ClassLocator
{

	/** @var string */
	private $baseDir;

	/** @var string */
	private $comopserAutoloaderPath;

	/** @var string */
	private $vendorDir;

	/** @var array */
	private $includePaths;

	/** @var array */
	private $excludePaths;


	public function __construct(string $baseDir, $includePaths = [], $excludePaths = [], bool $excludeVendor = true, string $vendorDir = 'vendor')
	{
		$this->baseDir = realpath($baseDir);
		$this->vendorDir = "$baseDir/$vendorDir";
		$this->comopserAutoloaderPath = $this->vendorDir . "/autoload.php";

		if ($includePaths instanceof RealPathList) {
			$this->includePaths = $includePaths;
		} else {
			$this->includePaths = new RealPathList($baseDir, $includePaths ?? []);
		}

		if ($excludePaths instanceof RealPathList) {
			$this->excludePaths = $excludePaths;
		} else {
			$this->excludePaths = new RealPathList($baseDir, $excludePaths ?? []);
		}

		if ($excludeVendor) {
			$this->excludePaths->add($this->vendorDir);
		}
	}


	public function getClasses(): \Generator
	{
		// TODO: Refactor this so that it builds a list of Class Locators and then introduce CompositeClassLocator.

		$autoloader = $this->loadComposerAutoloader($this->comopserAutoloaderPath);

		// Class map
		foreach ($autoloader->getClassMap() as $classname => $filename) {
			if (!$this->includePaths->isEmpty() && !$this->includePaths->contains($filename)) {
				continue;
			}
			if ($this->excludePaths->contains($filename)) {
				continue;
			}
			yield $classname;
		}

		if ($autoloader->isClassMapAuthoritative()) {
			// Class map is authoritative, i.e., it contains all existing classes.
			return;
		}

		// PSR-4
		foreach ($autoloader->getPrefixesPsr4() as $prefix => $dirs) {
			foreach ($dirs as $dir) {
				if ($this->excludePaths->contains($dir)) {
					continue;
				}
				$psr4Locator = new Psr4ClassLocator($prefix, $dir, $this->includePaths, $this->excludePaths);
				yield from $psr4Locator->getClasses();
			}
		}

		// PSR-0
		foreach ($autoloader->getPrefixes() as $dirs) {
			foreach ($dirs as $dir) {
				if ($this->excludePaths->contains($dir)) {
					continue;
				}
				$psr4Locator = new Psr0ClassLocator($dir, $this->includePaths, $this->excludePaths);
				yield from $psr4Locator->getClasses();
			}
		}
	}


	private function loadComposerAutoloader(string $autoloaderFilename): ClassLoader
	{
		$composerAutoloader = require($autoloaderFilename);
		if (!($composerAutoloader instanceof ClassLoader)) {
			throw new \RuntimeException("Composer autoloader not found in $autoloaderFilename");
		}
		return $composerAutoloader;
	}


	public function mapClassNameToFileName(string $className): ?string
	{
		// TODO: Implement mapClassNameToFileName() method.
	}


	public function mapFileNameToClassName(string $fileName): ?string
	{
		// TODO: Implement mapFileNameToClassName() method.
	}

}
