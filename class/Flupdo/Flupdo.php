<?php
/*
 * Copyright (c) 2013, Josef Kufner  <jk@frozen-doe.net>
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

namespace Smalldb\Flupdo;

/**
 * Extend PDO class with query builder starting methods. These methods are
 * simple factory & proxy to FlupdoBuilder.
 */
class Flupdo extends \PDO
{

	/**
	 * Returns fresh instance of Flupdo query builder.
	 */
	function __call($method, $args)
	{
		$class = __NAMESPACE__.'\\'.ucfirst($method).'Builder';
		if (!class_exists($class)) {
			throw new \BadMethodCallException('Undefined method "'.$method.'".');
		}
		$builder = new $class($this);
		if (!empty($args)) {
			$builder->__call($method, $args);
		}
		return $builder;
	}


	public function quoteIdent($ident)
	{
		if (is_array($ident)) {
			return array_map(function($ident) { return str_replace("`", "``", $ident); }, $ident);
		} else {
			return str_replace("`", "``", $ident);
		}
	}

}

