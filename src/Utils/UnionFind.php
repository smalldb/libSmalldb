<?php
/*
 * Copyright (c) 2016, Josef Kufner  <josef@kufner.cz>
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

namespace Smalldb\StateMachine\Utils;
use Smalldb\StateMachine\RuntimeException;

/**
 * Union-Find on scalar values (IDs or indexes)
 */
class UnionFind
{
	public $parents;

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->parents = [];
	}


	/**
	 * Create set $x, the $x may or may not exist before
	 */
	public function add($x)
	{
		if (!isset($this->parents[$x])) {
			$this->parents[$x] = $x;
		}
	}


	/**
	 * Create set $x, the $x must not exist before
	 */
	public function addUnique($x)
	{
		if (isset($this->parents[$x])) {
			throw new \RuntimeException('Element "'.$x.'" is already present in the union find.');
		}
		$this->parents[$x] = $x;
	}


	/**
	 * Union sets $a and $b.
	 */
	public function union($a, $b)
	{
		$ar = $this->find($a);
		$br = $this->find($b);

		if ($ar !== $br) {
			$this->parents[$br] = $ar;
		}

		/*
		$c = function($x) {
			return sprintf('<span style="background:#%x">%s</span>', crc32($x) & 0xffffff | 0x808080, $x);
		};

		echo '<br><b>', $c($a), ' == ', $c($b), "</b><br>";
		var_dump($this->parents);
		echo '    ', $c($a), ' … ', ($ar === $this->parents[$ar] ? $c($ar).' is root' : '<b>'.$c($ar).' is NOT root!</b>'), "<br>";
		echo '    ', $c($b), ' … ', ($br === $this->parents[$br] ? $c($br).' is root' : '<b>'.$c($br).' is NOT root!</b>'), "<br>";
		echo '    ', $c($ar), ' =?= ', $c($br), "<br>";
		echo '    ', $c($br), ' --&gt; ', $c($this->parents[$br]), "<br>";
		// */
	}


	/**
	 * Check if $x exists in the set.
	 */
	public function has($r)
	{
		return isset($this->parents[$r]);
	}


	/**
	 * Get representative of $x
	 */
	public function find($r)
	{
		if (!isset($this->parents[$r])) {
			throw new \InvalidArgumentException('Element "'.$r.'" is not defined.');
		}
		while ($r !== $this->parents[$r]) {
			$this->parents[$r] = $this->parents[$this->parents[$r]]; // Optimize nodes a little on the way to the root
			$r = $this->parents[$r];
		}
		return $r;
	}


	/**
	 * Get map of all items -> representative
	 */
	public function findAll()
	{
		$a = [];
		foreach ($this->parents as $x => $r) {
			$a[$x] = $this->find($x);
		}
		return $a;
	}


	/**
	 * Get a representative for each component.
	 */
	public function findDistinct()
	{
		$a = [];
		foreach ($this->parents as $x => $r) {
			$a[$this->find($x)] = null;
		}
		return array_keys($a);
	}


	/**
	 * Update keys in the $map to match representatives.
	 *
	 * @param array $map  Map to update
	 * @param callable|null $resolveConflict Callable (function($a, $b)) returning a new value to resolve conflicts if two keys has the same representative.
	 * @return array New map with old values and new keys.
	 */
	public function updateMap(array $map, callable $resolveConflict = null): array
	{
		$new_map = [];
		foreach ($map as $k => $v) {
			$new_k = $this->find($k);
			if (isset($new_map[$new_k])) {
				if ($resolveConflict !== null) {
					$new_map[$new_k] = $resolveConflict($v, $new_map[$new_k]);
				} else {
					throw new RuntimeException('Conflicting representatives in the map.');
				}
			} else {
				$new_map[$new_k] = $v;
			}
		}
		return $new_map;
	}


	/**
	 * Debug: Dump the UF tree to Dot for Graphviz
	 */
	public function dumpDot()
	{
		$dot = '';
		foreach ($this->parents as $x => $r) {
			$dot .= sprintf("\"[UF] %s\" [fillcolor=\"#%x\"];\n", $x, crc32($x) & 0xffffff | 0x808080);
			$dot .= "\"[UF] $r\" -> \"[UF] $x\" [arrowhead=none,arrowtail=vee];\n";
		}
		return $dot;
	}

}

