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

namespace Smalldb\StateMachine;

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
	 * Create set $x
	 */
	public function add($x)
	{
		$this->parents[$x] = $x;
	}


	/**
	 * Union sets $a and $b.
	 */
	public function union($a, $b)
	{
		$ar = $this->find($a);
		$br = $this->find($b);

		if ($ar === $br) {
			return;
		} else {
			$this->parents[$br] = $ar;
		}
	}


	/**
	 * Get representative of $x
	 */
	public function find($x)
	{
		$r = $x;
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

}

