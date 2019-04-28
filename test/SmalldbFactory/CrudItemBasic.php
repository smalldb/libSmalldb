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

namespace Smalldb\StateMachine\Test\SmalldbFactory;

use Smalldb\StateMachine\AnnotationReader;
use Smalldb\StateMachine\Provider\LambdaProvider;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItemMachine;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItemRef;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItemRepository;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItemTransitions;
use Smalldb\StateMachine\Test\Database\ArrayDao;


class CrudItemBasic implements SmalldbFactory
{

	public function createSmalldb(): Smalldb
	{
		$smalldb = new Smalldb();

		// Definition
		$reader = new AnnotationReader(CrudItemMachine::class);
		$definition = $reader->getStateMachineDefinition();

		// Repository
		$dao = new ArrayDao();
		$repository = new CrudItemRepository($smalldb, $dao);

		// Transitions implementation
		$transitionsImplementation = new CrudItemTransitions($repository, $dao);

		// Glue them together using a machine provider
		$machineProvider = (new LambdaProvider())
			->setReferenceClass(CrudItemRef::class)
			->setDefinition($definition)
			->setTransitionsImplementation($transitionsImplementation)
			->setRepository($repository);

		// Register state machine type
		$smalldb->registerMachineType($definition->getMachineType(), $machineProvider);

		return $smalldb;
	}

}
