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

class RealPathList extends PathList
{
	protected ?string $prefix = null;
	protected string $separator = DIRECTORY_SEPARATOR;

	public function __construct(?string $prefix = null, array $paths = [])
	{
		$this->prefix = $prefix !== '' && $prefix !== null ? $this->normalize($prefix) : null;
		parent::__construct($paths);
	}


	protected function normalize(string $path): string
	{
		if ($path[0] !== $this->separator && $this->prefix !== null) {
			$path = $this->prefix . $path;
		}
		$realpath = $this->realpath($path);
		return $realpath . $this->separator;
	}


	private function realpath(string $path): string
	{
		if ($this->separator === '\\') {
			$path = strtr($path, '/', $this->separator);
		}

		$leadingSeparator = ($path[0] === $this->separator ? $this->separator : '');

		$p = explode($this->separator, $path);

		$p = array_filter($p, function (string $f): bool {
			return $f !== '' && $f !== '.';
		});

		$r = [];
		foreach ($p as $i) {
			if ($i === '..') {
				if (empty($r)) {
					throw new \InvalidArgumentException("Invalid path: " . $path);
				}
				array_pop($r);
			} else {
				$r[] = $i;
			}
		}

		if (empty($r)) {
			throw new \InvalidArgumentException("Invalid path: " . $path);
		} else {
			return $leadingSeparator . join($this->separator, $r);
		}
	}

}
