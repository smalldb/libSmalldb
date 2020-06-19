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

use Smalldb\StateMachine\Graph\Graph;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\Test\Database\SymfonyDemoDatabase;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItem;
use Smalldb\StateMachine\Test\Example\Post\Post;
use Smalldb\StateMachine\Test\Example\Post\PostRepository;
use Smalldb\StateMachine\Utils\ClassLocator\ComposerClassLocator;
use Smalldb\StateMachine\Utils\ClassLocator\PathList;
use Smalldb\StateMachine\Utils\ClassLocator\Psr4ClassLocator;
use Smalldb\StateMachine\Utils\ClassLocator\RealPathList;


class ClassLocatorTest extends TestCase
{

	public function testPathList()
	{
		$pl = new PathList(["foo", "bar/bar"]);

		// Normalization
		$this->assertTrue($pl->containsExact("foo"));
		$this->assertTrue($pl->containsExact("foo" . DIRECTORY_SEPARATOR));

		// Exact matches
		$this->assertTrue($pl->contains("foo"));
		$this->assertTrue($pl->contains("bar/bar"));

		// Subpath matches
		$this->assertTrue($pl->contains("foo/bar"));
		$this->assertTrue($pl->contains("foo/foo/foo"));
		$this->assertTrue($pl->contains("bar/bar/bar"));

		// Not maching
		$this->assertFalse($pl->containsExact("bar"));
		$this->assertFalse($pl->contains("bar"));
		$this->assertFalse($pl->contains("bar/foo"));
	}


	public function testRealPathList()
	{
		$pl = new RealPathList(__DIR__, ["Example"]);

		$this->assertTrue($pl->containsExact("Example"));
		$this->assertTrue($pl->contains("Example"));
		$this->assertTrue($pl->contains(__DIR__ . "/Example"));
		$this->assertTrue($pl->contains(__DIR__ . "/Example/Bpmn"));

		$this->assertFalse($pl->contains(__DIR__));
		$this->assertFalse($pl->contains(__DIR__ . "/BadExample"));

		$this->assertTrue($pl->contains(__DIR__ . "/BadExample/../Example"));
		$this->assertFalse($pl->contains(__DIR__ . "/Example/../BadExample"));
	}


	public function testPsr4Locator()
	{
		$locator = new Psr4ClassLocator(__NAMESPACE__, __DIR__, [], ["BadExample", "Database", "output"]);
		$classes = iterator_to_array($locator->getClasses(), false);

		// A plain class
		$this->assertTrue(class_exists(PostRepository::class));
		$this->assertContains(PostRepository::class, $classes);

		// An abstract class
		$this->assertTrue(class_exists(Post::class));
		$this->assertContains(Post::class, $classes);

		// An interface
		$this->assertTrue(interface_exists(CrudItem::class));
		$this->assertContains(CrudItem::class, $classes);

		// Excluded class
		$this->assertTrue(class_exists(SymfonyDemoDatabase::class));
		$this->assertNotContains(SymfonyDemoDatabase::class, $classes);
	}


	public function testComposerLocator()
	{
		$locator = new ComposerClassLocator(dirname(__DIR__), ["src/Graph", "test"], ["test/BadExample", "test/Database", "test/output"]);
		$classes = iterator_to_array($locator->getClasses(), false);

		// A plain class outside tests
		$this->assertTrue(class_exists(Graph::class));
		$this->assertContains(Graph::class, $classes);
		$this->assertTrue(class_exists(Smalldb::class));
		$this->assertNotContains(Smalldb::class, $classes);

		// A plain class
		$this->assertTrue(class_exists(PostRepository::class));
		$this->assertContains(PostRepository::class, $classes);

		// An abstract class
		$this->assertTrue(class_exists(Post::class));
		$this->assertContains(Post::class, $classes);

		// An interface
		$this->assertTrue(interface_exists(CrudItem::class));
		$this->assertContains(CrudItem::class, $classes);

		// Excluded class
		$this->assertTrue(class_exists(SymfonyDemoDatabase::class));
		$this->assertNotContains(SymfonyDemoDatabase::class, $classes);
	}

}
