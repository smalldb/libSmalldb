<?php
/*
 * Copyright (c) 2014, Josef Kufner  <jk@frozen-doe.net>
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

namespace Smalldb\Cascade;

/**
 * Simple wrapper around Reference class. Use this to load properties and state 
 * of state machine.
 */
class LoadRefBlock extends BackendBlock
{

	/**
	 * Block inputs
	 */
	protected $inputs = array(
		'ref' => null,
		'preload' => true,	/// Preload properties?
	);

	/**
	 * Block inputs
	 */
	protected $outputs = array(
		'ref' => true,
		'state' => true,
		'properties' => true,
		'machine' => true,
		'machine_type' => true,
		'actions' => true,
		'*' => true,
		'done' => true,
	);

	private $ref;


	/**
	 * Block body
	 */
	public function main()
	{
		$this->ref = $this->in('ref');

		if ($this->ref === null) {
			return;
		}

		$this->out('ref', $this->ref);
		if ($this->in('preload')) {
			$this->out('properties', $this->ref->properties);
			$this->out('state', $this->ref->state);
		}
		$this->out('done', $this->ref->state != '');
	}


	/**
	 * Reference properties are mapped to block outputs.
	 */
	public function getOutput($name)
	{
		return $this->ref ? $this->ref->$name : null;
	}

}

