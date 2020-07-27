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

use Smalldb\StateMachine\AccessControlExtension\Definition\AccessControlExtensionPlaceholder;
use Smalldb\StateMachine\AccessControlExtension\Definition\AccessControlPolicy;
use Smalldb\StateMachine\AccessControlExtension\Definition\AccessPolicyExtensionPlaceholder;
use Smalldb\StateMachine\AccessControlExtension\Predicate as P;
use Smalldb\StateMachine\AccessControlExtension\Annotation\AC as A;
use Smalldb\StateMachine\InvalidArgumentException;


class AccessControlTest extends TestCaseWithDemoContainer
{

	public function testAllow()
	{
		$predicate = new P\Allow();
		$result = $predicate->evaluate();
		$this->assertTrue($result);
	}


	public function testDeny()
	{
		$predicate = new P\Deny();
		$result = $predicate->evaluate();
		$this->assertFalse($result);
	}


	public function testAllOf()
	{
		$this->assertTrue((new P\AllOf())->evaluate());
		$this->assertTrue((new P\AllOf(new P\Allow()))->evaluate());
		$this->assertFalse((new P\AllOf(new P\Deny()))->evaluate());

		$this->assertTrue((new P\AllOf(new P\Allow(), new P\Allow(), new P\Allow()))->evaluate());
		$this->assertTrue((new P\AllOf(new P\Allow(), new P\AllOf(new P\Allow(), new P\Allow())))->evaluate());
		$this->assertTrue((new P\AllOf(new P\AllOf(new P\Allow(), new P\Allow(), new P\Allow())))->evaluate());
		$this->assertTrue((new P\AllOf(new P\AllOf(new P\AllOf(new P\AllOf(new P\Allow())))))->evaluate());


		$this->assertFalse((new P\AllOf(new P\Allow(), new P\Deny()))->evaluate());
		$this->assertFalse((new P\AllOf(new P\Deny(), $this->neverAllow()))->evaluate());
		$this->assertFalse((new P\AllOf(new P\AllOf(new P\AllOf(new P\AllOf(new P\Deny())))))->evaluate());
	}


	public function testSomeOf()
	{
		$this->assertFalse((new P\SomeOf())->evaluate());
		$this->assertTrue((new P\SomeOf(new P\Allow()))->evaluate());
		$this->assertFalse((new P\SomeOf(new P\Deny()))->evaluate());
		$this->assertFalse((new P\SomeOf(new P\Deny(), new P\Deny(), new P\Deny()))->evaluate());
		$this->assertTrue((new P\SomeOf(new P\Deny(), new P\Allow(), $this->neverAllow()))->evaluate());
	}


	public function testNoneOf()
	{
		$this->assertTrue((new P\NoneOf())->evaluate());
		$this->assertTrue((new P\NoneOf(new P\Deny()))->evaluate());
		$this->assertTrue((new P\NoneOf(new P\Deny(), new P\Deny(), new P\Deny()))->evaluate());
		$this->assertFalse((new P\NoneOf(new P\Allow(), $this->neverAllow()))->evaluate());
	}


	private function neverAllow(): P\Allow
	{
		$notCalledAllow = $this->createMock(P\Allow::class);
		$notCalledAllow->expects($this->never())->method('evaluate');
		return $notCalledAllow;
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

		$n = $p->getNestedPredicates();
		$this->assertContainsOnlyInstancesOf(P\Predicate::class, $n);

		$nn = iterator_to_array($this->iterateNestedPredicatedRecursively($p), false);
		$this->assertContainsOnlyInstancesOf(P\Predicate::class, $nn);
		$this->assertCount(6, $nn);
	}


	private function iterateNestedPredicatedRecursively(P\Predicate $p): \Generator
	{
		yield $p;
		foreach ($p->getNestedPredicates() as $np) {
			yield from $this->iterateNestedPredicatedRecursively($np);
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

}
