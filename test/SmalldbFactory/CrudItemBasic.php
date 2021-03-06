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

use Smalldb\StateMachine\Definition\AnnotationReader\AnnotationReader;
use Smalldb\StateMachine\ClassGenerator\SmalldbClassGenerator;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilderFactory;
use Smalldb\StateMachine\Provider\LambdaProvider;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\Test\Database\ArrayDaoTables;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItem;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItemRepository;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItemTransitions;
use Smalldb\StateMachine\Test\TestTemplate\TestOutput;
use Smalldb\StateMachine\Transition\AllowingTransitionGuard;
use Smalldb\StateMachine\Transition\TransitionDecorator;


class CrudItemBasic implements SmalldbFactory
{

	public function createSmalldb(): Smalldb
	{
		$out = new TestOutput();

		$scg = new SmalldbClassGenerator('Smalldb\\GeneratedCode\\', $out->mkdir('generated'));
		$smalldb = new Smalldb();

		// Definition
		$reader = new AnnotationReader(StateMachineDefinitionBuilderFactory::createDefaultFactory());
		$definition = $reader->getStateMachineDefinition(new \ReflectionClass(CrudItem::class));

		// Repository
		$dao = new ArrayDaoTables();
		$repository = new CrudItemRepository($smalldb, $dao);

		// Transitions implementation
		$transitionsImplementation = $this->createCrudItemTransitions($repository, $dao);

		$realRefClassName = $scg->generateReferenceClass(CrudItem::class, $definition);

		// Glue them together using a machine provider
		$machineProvider = (new LambdaProvider())
			->setMachineType($definition->getMachineType())
			->setReferenceClass($realRefClassName)
			->setDefinition($definition)
			->setTransitionsDecorator($transitionsImplementation)
			->setRepository($repository);

		// Register state machine type
		$smalldb->registerMachineType($machineProvider, [CrudItem::class]);

		return $smalldb;
	}


	protected function createCrudItemTransitions(CrudItemRepository $repository, ArrayDaoTables $dao): TransitionDecorator
	{
		return new CrudItemTransitions(new AllowingTransitionGuard(), $repository, $dao);
	}

}
