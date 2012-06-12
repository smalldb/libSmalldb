<?php
/*
 * Copyright (c) 2012, Josef Kufner  <jk@frozen-doe.net>
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

namespace Entity;

/**
 * Basic entity driver. It is supposed to maintain all actions about entities,
 * while blocks are wrappers around these methods.
 *
 * This trait is included into block, so it can access additional inputs and
 * auth methods.
 */
trait EntityDriver {


	/**
	 * Initialize this driver before use. Returns true when successful, 
	 * otherwise block would terminate.
	 */
	protected function initializeEntityDriver()
	{
		return true;
	}


	/**
	 * Clean all used resources after use.
	 */
	protected function cleanupEntityDriver()
	{
	}


	/**
	 * Get full description of the entity.
	 */
	protected function describeEntity()
	{
		return array(
			'name' => _('Entity'),
			'driver' => __TRAIT__,
			'comment' => _('Basic entity.'),
			'properties' => array(
				'id' => array(),
			),
		);
	}


	/**
	 * Create specified entity, return it's ID.
	 */
	protected function createEntity($e)
	{
		return false;
	}


	/**
	 * Load entity by specified filters.
	 */
	protected function readEntity($filters)
	{
		return false;
	}


	/**
	 * Update specified entity.
	 */
	protected function updateEntity($id, $e)
	{
		return false;
	}


	/**
	 * Delete entity by primary key.
	 */
	protected function deleteEntity($id)
	{
		return false;
	}

}

