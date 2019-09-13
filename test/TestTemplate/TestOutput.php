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

namespace Smalldb\StateMachine\Test\TestTemplate;


class TestOutput
{
	/** @var string */
	protected $outputDir;


	public function __construct()
	{
		$this->outputDir = dirname(__DIR__) . '/output';
		if (!is_dir($this->outputDir)) {
			mkdir($this->outputDir);
		}
	}


	public function outputPath(string $basename): string
	{
		return $this->outputDir . '/' . basename($basename);
	}


	protected function resourcePath(string $basename): string
	{
		return dirname($this->outputDir) . '/resources/' . $basename;
	}


	public function mkdir(string $basename): string
	{
		$dir = $this->outputDir . '/' . basename($basename);
		if (!is_dir($dir)) {
			mkdir($dir);
		}
		return $dir;
	}


	/**
	 * Copy the file to the output directory and return relative URL of the file.
	 */
	public function resource(string $filename): string
	{
		$outputPath = $this->outputPath(basename($filename));
		$resourcePath = ($filename[0] == '/' ? $filename : $this->resourcePath($filename));

		if (!file_exists($resourcePath)) {
			throw new \InvalidArgumentException('Resource does not exist: ' . $resourcePath);
		}

		if (realpath($outputPath) !== realpath($resourcePath) && (!file_exists($outputPath) || filemtime($resourcePath) !== filemtime($outputPath))) {
			if (!copy($resourcePath, $outputPath)) {
				throw new \RuntimeException('Failed to copy resource: ' . $filename);
			}
		}
		return basename($outputPath);
	}


	/**
	 * Write content to the file in the output directory and return relative URL of the file.
	 *
	 * @see file_put_contents()
	 */
	public function writeResource(string $filename, $content): string
	{
		$outputPath = $this->outputPath(basename($filename));

		if (file_put_contents($outputPath, $content) === false) {
			throw new \RuntimeException('Failed to copy resource: ' . $filename);
		}

		return basename($outputPath);
	}

}
