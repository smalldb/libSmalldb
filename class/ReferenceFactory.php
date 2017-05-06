<?php
/*
 * Copyright (c) 2017, Josef Kufner  <josef@kufner.cz>
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
 * Factory to create typed references.
 *
 * Default implementation falls back to untyped AbstractBackend::ref().
 * 
 * Inherited classes should throw an exception in __call() magic method.
 */
class ReferenceFactory
{
	/** @var AbstractBackend */
	protected $backend;

	/**
	 * Constructor.
	 */
	public function __construct(AbstractBackend $backend)
	{
		$this->backend = $backend;
	}


	protected function __call($name, $args)
	{
		array_unshift($args, $name);
		return $this->backend->ref($args);
	}

}

