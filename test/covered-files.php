#!/usr/bin/env php
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

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Node\File;

require __DIR__ . '/../vendor/autoload.php';

/** @var CodeCoverage $coverage */
$coverage = require(__DIR__ . '/output/coverage/coverage.php');
$report = $coverage->getReport();

function printCovered(SebastianBergmann\CodeCoverage\Node\Directory $dir): void
{
	foreach ($dir->getFiles() as $file) {
		/** @var File $file */
		if ($file->getNumExecutedLines() > 0 && $file->getNumExecutableLines() > 0) {
			echo $file->getPath(), "\n";
		}
	}

	foreach ($dir->getDirectories() as $dir) {
		printCovered($dir);
	}
}

printCovered($report);
