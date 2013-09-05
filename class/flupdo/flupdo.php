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
class Flupdo extends \PDO
{
	/**
	 * Returns fresh instance of Flupdo query builder.
	 */
	function createFlupdoBuilder()
	{
		return new FlupdoBuilder($this);
	}


	function from(/* ... */)
	{
		$args = func_get_args();
		return $this->createFlupdoBuilder('SELECT')->__call('from', $args);
	}


		// 'method' => array('realMethodToCall', 'extra arguments - string or array',
		// 	array(/* buffers */),
		// 	'&' => 'merge with that one',
		// 	/* recursive */
		// ),


	function select(/* ... */)
	{
		$args = func_get_args();
		$builder = new SelectBuilder($this);
		return $builder->__call('select', $args);
	}


	function insert(/* ... */)
	{
		$args = func_get_args();
		return $this->createFlupdoBuilder('INSERT')->__call('insert', $args);
	}


	function update(/* ... */)
	{
		$args = func_get_args();
		return $this->createFlupdoBuilder('UPDATE')->__call('update', $args);
	}


	function delete(/* ... */)
	{
		$args = func_get_args();
		return $this->createFlupdoBuilder('DELETE')->__call('delete', $args);
	}

}

