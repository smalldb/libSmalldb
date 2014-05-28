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

namespace Smalldb\Cascade;

/**
 * Base class for backend-related blocks.
 */
abstract class BackendBlock extends \Cascade\Core\Block
{

	/**
	 * %Smalldb backend obtained from block storage.
	 */
	protected $smalldb;


	/**
	 * Setup block to act as expected. Configuration is done by BlockStorage.
	 */
	public function __construct($smalldb)
	{
		$this->smalldb = $smalldb;
	}

}

