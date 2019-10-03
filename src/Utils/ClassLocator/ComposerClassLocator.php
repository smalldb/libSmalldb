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


	public function __construct(string $baseDir, string $vendorDir = 'vendor')
	{
		$this->baseDir = $baseDir;
		$this->vendorDir = $vendorDir;
		$this->comopserAutoloaderPath = $this->vendorDir . "/autoload.php";
	}


	public function getClasses(): \Generator
	{
		$autoloader = $this->loadComposerAutoloader($this->baseDir, $this->comopserAutoloaderPath);

		// Class map
		foreach ($autoloader->getClassMap() as $classname => $filename) {
			yield $classname;
		}

		if ($autoloader->isClassMapAuthoritative()) {
			// Class map is authoritative, i.e., it contains all existing classes.
			return;
		}

		// PSR-4
		foreach ($autoloader->getPrefixesPsr4() as $prefix => $dirs) {
			foreach ($dirs as $dir) {
				$psr4Locator = new Psr4ClassLocator($prefix, $dir);
				yield from $psr4Locator->getClasses();
			}
		}

		// PSR-0
		foreach ($autoloader->getPrefixes() as $dir) {
			$psr4Locator = new Psr0ClassLocator($dir);
			yield from $psr4Locator->getClasses();
		}
	}


	private function loadComposerAutoloader(string $baseDir, string $composerAutoloaderPath): ClassLoader
	{
		$autoloadFile = "$baseDir/$composerAutoloaderPath";
		$composerAutoloader = require($autoloadFile);
		if (!($composerAutoloader instanceof ClassLoader)) {
			throw new \RuntimeException("Composer autoloader not found in $autoloadFile");
		}
		return $composerAutoloader;
	}

}
