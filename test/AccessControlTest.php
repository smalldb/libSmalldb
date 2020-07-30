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
use Smalldb\StateMachine\AccessControlExtension\Definition\AccessPolicyExtension;
use Smalldb\StateMachine\AccessControlExtension\Definition\AccessPolicyExtensionPlaceholder;
use Smalldb\StateMachine\AccessControlExtension\Predicate as P;
use Smalldb\StateMachine\AccessControlExtension\Annotation\AC as A;
use Smalldb\StateMachine\AccessControlExtension\SimpleTransitionGuard;
use Smalldb\StateMachine\Definition\ActionDefinition;
use Smalldb\StateMachine\Definition\Builder\PreprocessorList;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilder;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilderFactory;
use Smalldb\StateMachine\Definition\StateDefinition;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Definition\TransitionDefinition;
use Smalldb\StateMachine\InvalidArgumentException;
use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\SmalldbDefinitionBag;
use Smalldb\StateMachine\Test\Example\Post\Post;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;


class AccessControlTest extends TestCaseWithDemoContainer
{

	const ROLE_EDITOR = 'ROLE_EDITOR';
	const ROLE_USER = 'ROLE_USER';


	private function getRef(): ReferenceInterface
	{
		$m = $this->createMock(ReferenceInterface::class);
		$m->method('getState')->willReturn('');
		return $m;
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


	public function testAccessControlExtension()
	{
		$placeholder = new AccessControlExtensionPlaceholder();
		$placeholder->addPolicy(new AccessControlPolicy("Foo", new P\Allow()));
		$placeholder->addPolicy(new AccessControlPolicy("Bar", new P\Deny()));

		$ext = $placeholder->buildExtension();
		$policies = $ext->getPolicies();
		$this->assertCount(2, $policies);
		$this->assertContainsOnlyInstancesOf(AccessControlPolicy::class, $policies);

		$foo = $ext->getPolicy('Foo');
		$this->assertInstanceOf(P\Allow::class, $foo->getPredicate());
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

		$builder = StateMachineDefinitionBuilderFactory::createDefaultFactory()->createDefinitionBuilder();
		$builder->setMachineType("foo");

		$defaultPolicyAnnotation = new A\DefaultPolicy();
		$defaultPolicyAnnotation->policyName = $policyName;
		$defaultPolicyAnnotation->applyToBuilder($builder);

		$policyAnnotation = new A\DefinePolicy(["value" => [$policyName, new A\Allow()]]);
		$policyAnnotation->applyToBuilder($builder);

		$definition = $builder->build();
		$this->assertTrue($definition->hasExtension(AccessControlExtension::class));

		/** @var AccessControlExtension $ext */
		$ext = $definition->getExtension(AccessControlExtension::class);
		$defaultPolicyName = $ext->getDefaultPolicyName();
		$this->assertSame($policyName, $defaultPolicyName);

		$defaultPolicy = $ext->getPolicy($defaultPolicyName);
		$this->assertInstanceOf(AccessControlPolicy::class, $defaultPolicy);

		$defaultPredicate = $defaultPolicy->getPredicate();
		$this->assertInstanceOf(P\Predicate::class, $defaultPredicate);

		$this->assertNull($ext->getPolicy("some_undefined_policy"));
	}


	public function testLazyPredicate()
	{
		$transitionPredicates = [
			'foo' => [
				'' => [
					'yes' => new P\AllowCompiled(),
					'no' => (fn() => new P\DenyCompiled()),
				]
			]
		];

		$guard = new SimpleTransitionGuard($transitionPredicates);

		$this->assertTrue($guard->isAccessAllowed('foo', 'yes', $this->getRef()));
		$this->assertFalse($guard->isAccessAllowed('foo', 'no', $this->getRef()));
		$this->assertFalse($guard->isAccessAllowed('foo', 'maybe', $this->getRef()));

		// No access control defined => default allow
		$this->assertTrue($guard->isAccessAllowed('bar', 'yes', $this->getRef()));
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
		yield 'HasRole' => [ P\HasRole::class, [self::ROLE_USER], P\HasRoleCompiled::class ];
		yield 'IsOwner' => [ P\IsOwner::class, ['authorId'], P\IsOwnerCompiled::class ];
	}


	public function testCompileMissingPredicate()
	{
		$builder = new StateMachineDefinitionBuilder(new PreprocessorList());
		$builder->setMachineType('foo');
		$builder->addState('Exists');
		$trGood = $builder->addTransition('good', '', ['Exists']);
		$trBad = $builder->addTransition('bad', '', ['Exists']);

		$mock = $this->createMock(AccessPolicyExtensionPlaceholder::class);
		$mock->policyName = 'foo';

		$trGood->getExtensionPlaceholder(AccessPolicyExtensionPlaceholder::class)->policyName = 'good_policy';
		$trBad->getExtensionPlaceholder(AccessPolicyExtensionPlaceholder::class)->policyName = 'bad_policy';
		$builder->getExtensionPlaceholder(AccessControlExtensionPlaceholder::class)
			->addPolicy(new AccessControlPolicy('good_policy', new P\Allow()));

		$definition = $builder->build();
		$definitionBag = new SmalldbDefinitionBag();
		$definitionBag->addDefinition($definition);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("Access policy not found: bad_policy");
		SimpleTransitionGuard::compileTransitionPredicatesSymfony($definitionBag, new ContainerBuilder());
	}


	/**
	 * @throws \Exception
	 */
	public function testCompileWithContainer()
	{
		$ownerProperty = "authorId";

		$container = new ContainerBuilder();
		$containerAdapter = new P\SymfonyContainerAdapter($container);

		// Yes is always allowed
		$yes = new P\AllOf(new P\Allow());
		$yesCompiled = $yes->compile($containerAdapter);
		$this->assertInstanceOf(Reference::class, $yesCompiled);

		// No is always denied
		$no = new P\Deny();
		$noCompiled = $no->compile($containerAdapter);
		$this->assertInstanceOf(Reference::class, $noCompiled);

		// Role depends on mocked user's roles
		$hasRole = new P\HasRole(self::ROLE_EDITOR);
		$this->assertEquals(self::ROLE_EDITOR, $hasRole->getRole());
		$hasRoleCompiled = $hasRole->compile($containerAdapter);
		$this->assertInstanceOf(Reference::class, $hasRoleCompiled);

		// Allow Owner
		$isOwner = new P\IsOwner($ownerProperty);
		$this->assertEquals($ownerProperty, $isOwner->getOwnerProperty());
		$isOwnerCompiled = $isOwner->compile($containerAdapter);
		$this->assertInstanceOf(Reference::class, $isOwnerCompiled);

		// Configure Guard
		$transitionPredicates = [
			'foo' => [
				'' => [
					'yes' => $yesCompiled,
					'no' => $noCompiled,
					'role' => $hasRoleCompiled,
					'owner' => $isOwnerCompiled,
				]
			]
		];
		$container->autowire(SimpleTransitionGuard::class, SimpleTransitionGuard::class)
			->setArguments([$transitionPredicates])
			->setPublic(true);

		// Mocked security context for HasRole predicate
		$container->register(Security::class)
			->setSynthetic(true);

		$container->compile();

		// Create security mock
		$securityMock = $this->createMock(Security::class);
		$userRoles = [self::ROLE_USER];
		$userMock = $this->createMock(UserInterface::class);
		$userMock->method('getUserName')->willReturn(123);
		$userMock->method('getRoles')->willReturnCallback(function() use (&$userRoles) {
			return $userRoles;
		});
		$securityMock->method('getUser')->willReturn($userMock);
		$container->set(Security::class, $securityMock);

		// Get & check Guard
		/** @var SimpleTransitionGuard $guard */
		$guard = $container->get(SimpleTransitionGuard::class);
		$guardPredicates = $guard->getTransitionPredicates();
		$this->assertInstanceOf(P\AllofCompiled::class, $guardPredicates['foo']['']['yes'] ?? null);

		// Mock ref & definition
		$ref = $this->createMock(Post::class);
		$ref->method('getMachineType')->willReturn('foo');
		$ref->method('getAuthorId')->willReturn(123);
		$yesTransitionDefinition = $this->createMock(TransitionDefinition::class);
		$yesTransitionDefinition->method('getName')->willReturn('yes');
		$noTransitionDefinition = $this->createMock(TransitionDefinition::class);
		$noTransitionDefinition->method('getName')->willReturn('no');

		// Check Yes
		$this->assertTrue($guard->isAccessAllowed('foo', 'yes', $this->getRef()));
		$this->assertTrue($guard->isTransitionAllowed($ref, $yesTransitionDefinition));

		// Check No
		$this->assertFalse($guard->isAccessAllowed('foo', 'no', $this->getRef()));
		$this->assertFalse($guard->isTransitionAllowed($ref, $noTransitionDefinition));

		// Check HasRole before and after adding a role
		$this->assertFalse($guard->isAccessAllowed('foo', 'role', $this->getRef()));
		$userRoles[] = self::ROLE_EDITOR;
		$this->assertTrue($guard->isAccessAllowed('foo', 'role', $this->getRef()));

		// Check IsOwner
		$this->assertTrue($guard->isAccessAllowed('foo', 'owner', $ref));

		// Check machine with no access control at all
		$this->assertTrue($guard->isAccessAllowed('bar', 'something', $this->getRef()));

		// Check transition with no predicate but with other defined transitions
		$this->assertFalse($guard->isAccessAllowed('foo', 'something', $this->getRef()));
	}

}
