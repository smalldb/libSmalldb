<?php declare(strict_types = 1);
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
	public ?string $source = null;

	/** @var string[] */
	public array $targets = [];


	public function __construct(array $values)
	{
		if (isset($values['value'])) {
			if (is_array($values['value']) && count($values['value']) === 2
				&& is_string($values['value'][0])
				&& is_array($values['value'][1]) && count($values['value'][1]) > 0)
			{
				list($this->source, $this->targets) = $values['value'];
			} else {
				throw new \InvalidArgumentException("Transition annotation requires none or two arguments - a source state and a list of target states.");
			}
		}
	}


	public function definesTransition(): bool
	{
		return $this->source !== null && !empty($this->targets);
	}

}
