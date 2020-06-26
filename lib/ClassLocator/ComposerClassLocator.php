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

use Composer\Autoload\ClassLoader;


class ComposerClassLocator extends CompositeClassLocator implements ClassLocator
{
	private bool $setupComplete = false;
	private string $baseDir;
	private string $comopserAutoloaderPath;
	private string $vendorDir;
	private RealPathList $includePaths;
	private RealPathList $excludePaths;


	public function __construct(string $baseDir, $includePaths = [], $excludePaths = [], bool $excludeVendor = true, string $vendorDir = 'vendor')
	{
		parent::__construct();

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


	private function setup()
	{
		if ($this->setupComplete) {
			return;
		}

		$autoloader = $this->loadComposerAutoloader($this->comopserAutoloaderPath);

		// Class map
		$classMap = [];
		foreach ($autoloader->getClassMap() as $classname => $filename) {
			if (!$this->includePaths->isEmpty() && !$this->includePaths->contains($filename)) {
				continue;
			}
			if ($this->excludePaths->contains($filename)) {
				continue;
			}
			$classMap[$classname] = $filename;
		}
		if (!empty($classMap)) {
			$this->addClassLocator(new ClassMapClassLocator($classMap));
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
				$this->addClassLocator(new Psr4ClassLocator($prefix, $dir, $this->includePaths, $this->excludePaths));
			}
		}

		// PSR-0
		foreach ($autoloader->getPrefixes() as $dirs) {
			foreach ($dirs as $dir) {
				if ($this->excludePaths->contains($dir)) {
					continue;
				}
				$this->addClassLocator(new Psr0ClassLocator($dir, $this->includePaths, $this->excludePaths));
			}
		}

		$this->setupComplete = true;
	}


	private function loadComposerAutoloader(string $autoloaderFilename): ClassLoader
	{
		$composerAutoloader = require($autoloaderFilename);
		if (!($composerAutoloader instanceof ClassLoader)) {
			throw new \RuntimeException("Composer autoloader not found in $autoloaderFilename");
		}
		return $composerAutoloader;
	}


	public function getClasses(): \Generator
	{
		$this->setup();
		yield from parent::getClasses();
	}


	public function mapClassNameToFileName(string $className): ?string
	{
		$this->setup();
		return parent::mapClassNameToFileName($className);
	}


	public function mapFileNameToClassName(string $fileName): ?string
	{
		$this->setup();
		return parent::mapFileNameToClassName($fileName);
	}

}
