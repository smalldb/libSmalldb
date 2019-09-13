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

namespace Smalldb\StateMachine\Utils;


/**
 * Trait CopyConstructorTrait
 *
 * Copy properties from the source entity to create a clone of a different
 * kind. This constructor expects that all properties are protected, and
 * some of the children are lazy to load all data at once.
 */
trait CopyConstructorTrait
{

	/**
	 * PostDataImmutable copy constructor.
	 */
	public function __construct(self $src = null)
	{
		if ($src !== null) {
			$src->loadData();
			foreach (get_object_vars($this) as $k => $v) {
				$this->$k = $src->$k;
			}
		}
	}


	protected function loadData(): void
	{
		// Implement this if this class has lazy children.
	}

}
