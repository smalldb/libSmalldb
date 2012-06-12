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

class B_entity__read extends Block {

	use Entity\EntityDriver;

	protected $inputs = array(
		'id' => false,		// ID of requested entity
	);

	protected $outputs = array(
		'id' => true,		// ID of loaded entity
		'entity' => true,	// Loaded entity
		'done' => true,		// True if at least one entity has been loaded.
	);

	const force_exec = true;


	public function main()
	{
		if (!$this->initializeEntityDriver()) {
			return;
		}

		$filters = $this->collectFilters();
		if ($filters === false) {
			return;
		}

		$e = $this->loadEntity($filters);

		if (!empty($e)) {
			$this->out('id', $e['id']);
			$this->out('entity', $e);
			$this->out('done', true);
		} else {
			$this->out('done', false);
		}

		$this->cleanupEntityDriver();
	}


	/**
	 * Collect filter values from inputs into array and do basic sanity checks.
	 */
	protected function collectFilters()
	{
		$filters = array();

		$id = $this->id();
		if ($id !== false) {
			$filters['id'] = $id;
		}

		return $filters;
	}

}



