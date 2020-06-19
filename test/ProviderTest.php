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


namespace Smalldb\StateMachine\Test;

use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\InvalidArgumentException;
use Smalldb\StateMachine\Provider\ContainerProvider;
use Smalldb\StateMachine\Provider\LambdaProvider;
use Smalldb\StateMachine\Provider\SmalldbProviderInterface;
use Smalldb\StateMachine\SmalldbRepositoryInterface;
use Smalldb\StateMachine\Test\Example\Post\PostRepository;
use Smalldb\StateMachine\Test\SmalldbFactory\CrudItemContainer;
use Smalldb\StateMachine\Transition\TransitionDecorator;


class ProviderTest extends TestCase
{

	/**
	 * @dataProvider providerProvider
	 */
	public function testUninitializedDefinition(SmalldbProviderInterface $provider)
	{
		$this->expectException(\LogicException::class);
		$provider->getDefinition();
	}


	/**
	 * @dataProvider providerProvider
	 */
	public function testUninitializedRepository(SmalldbProviderInterface $provider)
	{
		$this->expectException(\LogicException::class);
		$provider->getRepository();
	}


	/**
	 * @dataProvider providerProvider
	 */
	public function testUninitializedTransitionsDecorator(SmalldbProviderInterface $provider)
	{
		$this->expectException(\LogicException::class);
		$provider->getTransitionsDecorator();
	}


	public function providerProvider()
	{
		yield 'LambdaProvider' => [new LambdaProvider()];
		yield 'ContainerProvider' => [new ContainerProvider((new CrudItemContainer())->createContainer())];
	}


	public function testLambdaProviderSetters()
	{
		$definitionFactoryCalled = false;
		$definition = $this->createMock(StateMachineDefinition::class);

		$repositoryFactoryCalled = false;
		$repository = $this->createMock(SmalldbRepositoryInterface::class);

		$transitionDecoratorFactoryCalled = false;
		$transitionDecorator = $this->createMock(TransitionDecorator::class);

		$provider = new LambdaProvider();

		$provider->setDefinitionFactory(function() use (&$definitionFactoryCalled, $definition) {
			$this->assertFalse($definitionFactoryCalled);
			$definitionFactoryCalled = true;
			return $definition;
		});

		$provider->setRepositoryFactory(function() use (&$repositoryFactoryCalled, $repository) {
			$this->assertFalse($repositoryFactoryCalled);
			$repositoryFactoryCalled = true;
			return $repository;
		});

		$provider->setTransitionsDecoratorFactory(function() use (&$transitionDecoratorFactoryCalled, $transitionDecorator) {
			$this->assertFalse($transitionDecoratorFactoryCalled);
			$transitionDecoratorFactoryCalled = true;
			return $transitionDecorator;
		});

		$this->assertEquals($definition, $provider->getDefinition());
		$this->assertTrue($definitionFactoryCalled);

		$this->assertEquals($repository, $provider->getRepository());
		$this->assertTrue($repositoryFactoryCalled);

		$this->assertEquals($transitionDecorator, $provider->getTransitionsDecorator());
		$this->assertTrue($transitionDecoratorFactoryCalled);
	}


	public function testLambdaProviderClosureMap()
	{
		$definition = $this->createMock(StateMachineDefinition::class);
		$repository = $this->createMock(SmalldbRepositoryInterface::class);
		$transitionDecorator = $this->createMock(TransitionDecorator::class);

		$provider = new LambdaProvider([
			LambdaProvider::DEFINITION => function() use ($definition) { return $definition; },
			LambdaProvider::REPOSITORY => function() use ($repository) { return $repository; },
			LambdaProvider::TRANSITIONS_DECORATOR => function() use ($transitionDecorator) { return $transitionDecorator; },
		]);

		$this->assertEquals($definition, $provider->getDefinition());
		$this->assertEquals($repository, $provider->getRepository());
		$this->assertEquals($transitionDecorator, $provider->getTransitionsDecorator());
	}


	public function testMissingReferenceClass()
	{
		$provider = new LambdaProvider();

		$this->expectException(InvalidArgumentException::class);
		$provider->setReferenceClass('Fooo');
	}

}
