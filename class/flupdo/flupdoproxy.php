<?php
/*
 * Copyright (c) 2013, Josef Kufner  <jk@frozen-doe.net>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. Neither the name of the author nor the names of its contributors
 *    may be used to endorse or promote products derived from this software
 *    without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE REGENTS AND CONTRIBUTORS ``AS IS'' AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED.  IN NO EVENT SHALL THE REGENTS OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
 * OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
 * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
 * SUCH DAMAGE.
 */

namespace Smalldb\Flupdo;

/**
 * Extend PDO class with query builder starting methods. These methods are 
 * simple factory & proxy to FlupdoBuilder.
 */
class FlupdoProxy
{
	private $pdo;

	function __construct(\PDO $pdo)
	{
		$this->pdo = $pdo;
	}

	/**
	 * Call $this->pdo method if exist, otherwise return fresh instance
	 * of Flupdo query builder.
	 */
	function __call($method, $args)
	{
		// Call original method if exists
		if (method_exists($this->pdo, $method)) {
			return call_user_func_array(array($this->pdo, $method), $args);
		}

		// Almost the same invocation code as in Flupdo::__call
		$class = __NAMESPACE__.'\\'.ucfirst($method).'Builder';
		if (!class_exists($class)) {
			if (method_exists($this->pdo, '__call')) {
				return $this->pdo->__call($method, $args);
			} else {
				throw new \BadMethodCallException('Undefined method "'.$method.'".');
			}
		}
		$builder = new $class($this);
		if (!empty($args)) {
			$builder->__call($method, $args);
		}
		return $builder;
	}

}

