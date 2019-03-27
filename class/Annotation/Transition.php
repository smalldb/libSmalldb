<?php
/*
 * Copyright (c) 2019, Josef Kufner  <josef@kufner.cz>
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

namespace Smalldb\StateMachine\Annotation;

/**
 * Transition annotation
 *
 * @Annotation
 * @Target({"METHOD"})
 */
class Transition
{
	/**
	 * @var string
	 * @Required
	 */
	public $source;

	/**
	 * @var array
	 * @Required
	 */
	public $targets;


	public function __construct(array $values)
	{
		if (count($values) !== 2) {
			throw new \InvalidArgumentException("Transition annotation requires two arguments - a source state and a list of target states.");
		}

		list($this->source, $this->targets) = $values['value'];
	}
}
