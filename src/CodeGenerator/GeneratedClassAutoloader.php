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

namespace Smalldb\StateMachine\CodeGenerator;


class GeneratedClassAutoloader
{

	/** @var string */
	private $namespace;

	/** @var string */
	private $directory;

	/** @var int */
	private $namespaceLen;

	/** @var bool */
	private $prependAutoloader;


	public function __construct(string $namespace, string $directory, bool $prependAutoloader = false)
	{
		$this->namespace = $namespace[-1] === '\\' ? $namespace : $namespace . "\\";
		$this->namespaceLen = strlen($namespace);
		$this->directory = $directory[-1] === DIRECTORY_SEPARATOR ? $directory : $directory . DIRECTORY_SEPARATOR;
		$this->prependAutoloader = $prependAutoloader;
	}


	public function registerLoader()
	{
		spl_autoload_register(function($class) {
			if (strncmp($class, $this->namespace, $this->namespaceLen) === 0) {
				require $this->directory . substr($class, $this->namespaceLen + 1) . '.php';
			}
		}, true, $this->prependAutoloader);
	}

}
