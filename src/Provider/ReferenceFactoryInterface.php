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

namespace Smalldb\StateMachine\Provider;

use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\Smalldb;


interface ReferenceFactoryInterface
{

	/**
	 * Create Reference object without using a repository (so it does
	 * not have to be initialized).
	 */
	public function createReference(Smalldb $smalldb, $id): ReferenceInterface;

	/**
	 * Get the Reference class which createReference() instantiates,
	 * so that repository can call its static factory method to
	 * create a preheated Reference object.
	 */
	public function getReferenceClass(): string;

}
