<?php declare(strict_types = 1);
/*
 * Copyright (c) 2020, Josef Kufner  <josef@kufner.cz>
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

use Generator;
use Smalldb\StateMachine\AccessControlExtension\Definition\AccessControlExtension;
use Smalldb\StateMachine\AccessControlExtension\Definition\AccessControlExtensionPlaceholder;
use Smalldb\StateMachine\AccessControlExtension\Definition\AccessControlPolicy;
use Smalldb\StateMachine\AccessControlExtension\Definition\AccessPolicyExtensionPlaceholder;
use Smalldb\StateMachine\AccessControlExtension\Predicate as P;
use Smalldb\StateMachine\AccessControlExtension\Annotation\AC as A;
use Smalldb\StateMachine\AccessControlExtension\SimpleTransitionGuard;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilderFactory;
use Smalldb\StateMachine\InvalidArgumentException;
use Smalldb\StateMachine\ReferenceInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;


class AccessControlTest extends TestCaseWithDemoContainer
{

	private function getRef(): ReferenceInterface
	{
		return $this->createMock(ReferenceInterface::class);
	}


	private function neverAllow(): P\AllowCompiled
	{
		$notCalledAllow = $this->createMock(P\AllowCompiled::class);
		$notCalledAllow->expects($this->never())->method('evaluate');
		return $notCalledAllow;
	}


	public function testAllow()
	{
		$ref = $this->getRef();
		$predicate = new P\AllowCompiled();
		$result = $predicate->evaluate($ref);
		$this->assertTrue($result);
	}


	public function testDeny()
	{
		$ref = $this->getRef();
		$predicate = new P\DenyCompiled();
		$result = $predicate->evaluate($ref);
		$this->assertFalse($result);
	}


	public function testAllOf()
	{
		$ref = $this->getRef();
		$this->assertTrue((new P\AllOfCompiled())->evaluate($ref));
		$this->assertTrue((new P\AllOfCompiled(new P\AllowCompiled()))->evaluate($ref));
		$this->assertFalse((new P\AllOfCompiled(new P\DenyCompiled()))->evaluate($ref));

		$this->assertTrue((new P\AllOfCompiled(new P\AllowCompiled(), new P\AllowCompiled(), new P\AllowCompiled()))->evaluate($ref));
		$this->assertTrue((new P\AllOfCompiled(new P\AllowCompiled(), new P\AllOfCompiled(new P\AllowCompiled(), new P\AllowCompiled())))->evaluate($ref));
		$this->assertTrue((new P\AllOfCompiled(new P\AllOfCompiled(new P\AllowCompiled(), new P\AllowCompiled(), new P\AllowCompiled())))->evaluate($ref));
		$this->assertTrue((new P\AllOfCompiled(new P\AllOfCompiled(new P\AllOfCompiled(new P\AllOfCompiled(new P\AllowCompiled())))))->evaluate($ref));


		$this->assertFalse((new P\AllOfCompiled(new P\AllowCompiled(), new P\DenyCompiled()))->evaluate($ref));
		$this->assertFalse((new P\AllOfCompiled(new P\DenyCompiled(), $this->neverAllow()))->evaluate($ref));
		$this->assertFalse((new P\AllOfCompiled(new P\AllOfCompiled(new P\AllOfCompiled(new P\AllOfCompiled(new P\DenyCompiled())))))->evaluate($ref));
	}


	public function testSomeOf()
	{
		$ref = $this->getRef();
		$this->assertFalse((new P\SomeOfCompiled())->evaluate($ref));
		$this->assertTrue((new P\SomeOfCompiled(new P\AllowCompiled()))->evaluate($ref));
		$this->assertFalse((new P\SomeOfCompiled(new P\DenyCompiled()))->evaluate($ref));
		$this->assertFalse((new P\SomeOfCompiled(new P\DenyCompiled(), new P\DenyCompiled(), new P\DenyCompiled()))->evaluate($ref));
		$this->assertTrue((new P\SomeOfCompiled(new P\DenyCompiled(), new P\AllowCompiled(), $this->neverAllow()))->evaluate($ref));
	}


	public function testNoneOf()
	{
		$ref = $this->getRef();
		$this->assertTrue((new P\NoneOfCompiled())->evaluate($ref));
		$this->assertTrue((new P\NoneOfCompiled(new P\DenyCompiled()))->evaluate($ref));
		$this->assertTrue((new P\NoneOfCompiled(new P\DenyCompiled(), new P\DenyCompiled(), new P\DenyCompiled()))->evaluate($ref));
		$this->assertFalse((new P\NoneOfCompiled(new P\AllowCompiled(), $this->neverAllow()))->evaluate($ref));
	}


	public function testOperatorAnnotations()
	{
		$this->assertInstanceOf(P\Allow::class, (new A\Allow())->buildPredicate());
		$this->assertInstanceOf(P\Deny::class, (new A\Deny())->buildPredicate());
		$this->assertInstanceOf(P\AllOf::class, (new A\AllOf())->buildPredicate());
		$this->assertInstanceOf(P\SomeOf::class, (new A\SomeOf())->buildPredicate());
		$this->assertInstanceOf(P\NoneOf::class, (new A\NoneOf())->buildPredicate());
	}


	public function testNestedPredicates()
	{
		$p = new P\AllOf(
			new P\SomeOf(
				new P\Allow(),
				new P\Deny()
			),
			new P\NoneOf(
				new P\Deny()
			)
		);

		$n = $p instanceof P\PredicateOperator ? $p->getNestedPredicates() : [];
		$this->assertContainsOnlyInstancesOf(P\Predicate::class, $n);

		$nn = iterator_to_array($this->iterateNestedPredicatedRecursively($p), false);
		$this->assertContainsOnlyInstancesOf(P\Predicate::class, $nn);
		$this->assertCount(6, $nn);
	}


	private function iterateNestedPredicatedRecursively(P\Predicate $p): Generator
	{
		yield $p;
		if ($p instanceof P\PredicateOperator) {
			foreach ($p->getNestedPredicates() as $np) {
				yield from $this->iterateNestedPredicatedRecursively($np);
			}
		}
	}


	public function testDuplicateAccessPolicyInPlaceholder()
	{
		$ext = new AccessControlExtensionPlaceholder();
		$ext->addPolicy(new AccessControlPolicy("Foo", new P\Allow()));
		$ext->addPolicy(new AccessControlPolicy("Bar", new P\Deny()));

		$this->expectException(InvalidArgumentException::class);
		$ext->addPolicy(new AccessControlPolicy("Foo", new P\Deny()));
	}


	public function testEmptyAccessControlExtension()
	{
		$placeholder = new AccessControlExtensionPlaceholder();
		$emptyExt = $placeholder->buildExtension();
		$this->assertNull($emptyExt);
	}


	public function testAccessControlPolicy()
	{
		$predicate = new P\Allow();
		$name = "Foo";
		$p = new AccessControlPolicy($name, $predicate);
		$this->assertSame($name, $p->getName());
		$this->assertSame($predicate, $p->getPredicate());
	}


	public function testAccessPolicyExtension()
	{
		$name = "Foo";
		$placeholder = new AccessPolicyExtensionPlaceholder();
		$placeholder->setPolicyName($name);
		$ext = $placeholder->buildExtension();
		$this->assertSame($name, $ext->getPolicyName());
	}


	public function testEmptyAccessPolicyExtension()
	{
		$placeholder = new AccessPolicyExtensionPlaceholder();
		$ext = $placeholder->buildExtension();
		$this->assertNull($ext);
	}


	public function testDefaultPolicy()
	{
		$policyName = "default_policy";

		$annotation = new A\DefaultPolicy();
		$annotation->policyName = $policyName;

		$builder = StateMachineDefinitionBuilderFactory::createDefaultFactory()->createDefinitionBuilder();
		$builder->setMachineType("foo");
		$annotation->applyToBuilder($builder);

		$definition = $builder->build();
		$this->assertTrue($definition->hasExtension(AccessControlExtension::class));

		/** @var AccessControlExtension $ext */
		$ext = $definition->getExtension(AccessControlExtension::class);
		$this->assertSame($policyName, $ext->getDefaultPolicyName());
	}


	/**
	 * @dataProvider predicateCompileProvider
	 */
	public function testPredicateCompile(string $predicateClassName, ?array $args, string $expectedCompiledClassName)
	{
		$compiledClassName = null;
		$compiledArgs = null;

		$container = $this->createMock(P\ContainerAdapter::class);
		$container->expects($this->once())->method('registerService')
			->willReturnCallback(function($id, string $className, ?array $args = null) use (&$compiledClassName, &$compiledArgs) {
				$compiledClassName = $className;
				$compiledArgs = $args;
			});

		/** @var P\Predicate $predicate */
		$predicate = new $predicateClassName(...($args ?? []));
		$predicate->compile($container);

		$this->assertNotNull($compiledClassName, 'ContainerAdapter::registerService has not been called.');
		$this->assertSame($expectedCompiledClassName, $compiledClassName);

		// Compiled args should be the same as the original args.
		$this->assertSame($args, $compiledArgs);
	}


	public function predicateCompileProvider()
	{
		yield 'Allow' => [ P\Allow::class, null, P\AllowCompiled::class ];
		yield 'Deny' => [ P\Deny::class, null, P\DenyCompiled::class ];
		yield 'AllOf' => [ P\AllOf::class, [], P\AllOfCompiled::class ];
		yield 'SomeOf' => [ P\SomeOf::class, [], P\SomeOfCompiled::class ];
		yield 'NoneOf' => [ P\NoneOf::class, [], P\NoneOfCompiled::class ];
		yield 'HasRole' => [ P\HasRole::class, ['ROLE_USER'], P\HasRoleCompiled::class ];
		yield 'IsOwner' => [ P\IsOwner::class, ['authorId'], P\IsOwnerCompiled::class ];
	}


	/**
	 * @throws \Exception
	 */
	public function testCompileWithContainer()
	{
		$container = new ContainerBuilder();
		$ca = new P\SymfonyContainerAdapter($container);

		$yes = new P\AllOf(new P\Allow());
		$yesCompiled = $yes->compile($ca);
		$this->assertInstanceOf(Reference::class, $yesCompiled);

		$no = new P\Deny();
		$noCompiled = $no->compile($ca);
		$this->assertInstanceOf(Reference::class, $noCompiled);

		$transitionPredicates = [
			'foo' => [
				'yes' => $yesCompiled,
				'no' => $noCompiled,
			]
		];

		$container->autowire(SimpleTransitionGuard::class, SimpleTransitionGuard::class)
			->setArguments([$transitionPredicates])
			->setPublic(true);

		$container->compile();

		/** @var SimpleTransitionGuard $guard */
		$guard = $container->get(SimpleTransitionGuard::class);

		$yesAllowed = $guard->isAccessAllowed('foo', 'yes', $this->getRef());
		$this->assertTrue($yesAllowed);

		$noAllowed = $guard->isAccessAllowed('foo', 'no', $this->getRef());
		$this->assertFalse($noAllowed);

		$guardPredicates = $guard->getTransitionPredicates();
		$this->assertInstanceOf(P\AllofCompiled::class, $guardPredicates['foo']['yes'] ?? null);
	}

}
