<?php
/*
 * Copyright (c) 2012, Josef Kufner  <jk@frozen-doe.net>
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

class B_smalldb__session_machine extends \Cascade\Core\Block
{

	protected $inputs = array(
	);

	protected $outputs = array(
		'ref' => true,
		'state' => true,
		'properties' => true,
		'*' => true,
		'done' => true,
	);

	const force_exec = true;

	private $ref;


	public function main()
	{
		$auth = $this->auth();
		if ($auth instanceof \Smalldb\Cascade\Auth) {
			try {
				$this->ref = $auth->getSessionMachine();
				if ($this->ref === null) {
					return;
				}

				$this->out('ref', $this->ref);
				$this->out('properties', $this->ref->properties);
				$this->out('state', $this->ref->state);
				$this->out('done', !!$this->ref);
			}
			catch(\Smalldb\Statemachine\RuntimeException $ex) {
				//error_msg('Failed to unref reference: %s', $ex->getMessage());
				$this->ref = null;
				$this->out('done', false);
			}
		}
	}


	/**
	 * Reference properties are mapped to block outputs.
	 */
	public function getOutput($name)
	{
		return $this->ref ? $this->ref->$name : null;
	}
}


