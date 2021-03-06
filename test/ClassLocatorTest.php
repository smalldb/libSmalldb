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

use ReflectionClass;
use Smalldb\ClassLocator\BrokenClassLogger;
use Smalldb\StateMachine\Annotation\StateMachine;
use Smalldb\Graph\Graph;
use Smalldb\StateMachine\InvalidArgumentException;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\Test\BadExample\BrokenClass\MissingImplementationDummy;
use Smalldb\StateMachine\Test\BadExample\BrokenClass\MissingInterfaceDummy;
use Smalldb\StateMachine\Test\BadExample\BrokenClass\MissingParentDummy;
use Smalldb\StateMachine\Test\BadExample\BrokenClass\MissingTraitDummy;
use Smalldb\StateMachine\Test\BadExample\BrokenClass\MissingTypehintsDummy;
use Smalldb\StateMachine\Test\Database\SymfonyDemoDatabase;
use Smalldb\StateMachine\Test\Example\CrudItem\CrudItem;
use Smalldb\StateMachine\Test\Example\Post\Post;
use Smalldb\StateMachine\Test\Example\Post\PostRepository;
use Smalldb\StateMachine\Test\Example\Tag\Tag;
use Smalldb\ClassLocator\ClassLocator;
use Smalldb\ClassLocator\ComposerClassLocator;
use Smalldb\ClassLocator\PathList;
use Smalldb\ClassLocator\Psr4ClassLocator;
use Smalldb\ClassLocator\RealPathList;


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
		$locator->setBrokenClassHandler($brokenClassLogger = new BrokenClassLogger());
		$classes = iterator_to_array($locator->getClasses(), false);

		// A plain class
		$this->assertClassExists(PostRepository::class);
		$this->assertContainsEquals(PostRepository::class, $classes);

		// An abstract class
		$this->assertClassExists(Post::class);
		$this->assertContainsEquals(Post::class, $classes);

		// An interface
		$this->assertClassExists(CrudItem::class);
		$this->assertContainsEquals(CrudItem::class, $classes);

		// Excluded class
		$this->assertClassExists(SymfonyDemoDatabase::class);
		$this->assertNotContainsEquals(SymfonyDemoDatabase::class, $classes);

		$brokenClasses = $brokenClassLogger->getBrokenClasses();
		$this->assertEmpty($brokenClasses);
	}


	private function checkClassNameToFileName(ClassLocator $locator, string $className, bool $shouldBeFound = true)
	{
		$expectedFilename = (new ReflectionClass($className))->getFileName();
		$mappedFileName = $locator->mapClassNameToFileName($className);

		if ($shouldBeFound) {
			$this->assertFileExists($mappedFileName);
			$this->assertEquals(realpath($expectedFilename), realpath($mappedFileName));
		} else {
			$this->assertNull($mappedFileName);
		}
	}


	private function checkFileNameToClassName(ClassLocator $locator, string $className, bool $shouldBeFound = true)
	{
		$this->assertClassExists($className);
		$filename = (new ReflectionClass($className))->getFileName();

		$mappedClassName = $locator->mapFileNameToClassName($filename);

		if ($shouldBeFound) {
			$this->assertEquals(realpath($className), $mappedClassName === null ? null : realpath($mappedClassName));
		} else {
			$this->assertNull($mappedClassName);
		}
	}


	public function testPsr4LocatorMappingClassNameToFileName()
	{
		$locator = new Psr4ClassLocator(__NAMESPACE__, __DIR__, [], ["BadExample", "Database", "output"]);

		$this->checkClassNameToFileName($locator, Tag::class);
		$this->checkClassNameToFileName($locator, StateMachine::class, false);

		$this->expectException(InvalidArgumentException::class);
		$locator->mapClassNameToFileName(__NAMESPACE__ . '\\..\\..\\etc\\passwd');
	}


	public function testPsr4LocatorMappingFileNameToClassName()
	{
		$locator = new Psr4ClassLocator(__NAMESPACE__, __DIR__, [], ["BadExample", "Database", "output"]);

		$this->checkFileNameToClassName($locator, Tag::class);
		$this->checkFileNameToClassName($locator, StateMachine::class, false);

		$this->expectException(InvalidArgumentException::class);
		$locator->mapFileNameToClassName(__DIR__ . '/../../etc/passwd');
	}


	public function testComposerLocator()
	{
		$locator = new ComposerClassLocator(dirname(__DIR__), ["lib/Graph", "test"], ["test/BadExample", "test/Database", "test/output"]);
		$classes = iterator_to_array($locator->getClasses(), false);

		$this->assertContainsOnlyInstancesOf(ClassLocator::class, $locator->getClassLocators());

		// A plain class outside tests
		$this->assertClassExists(Graph::class);
		$this->assertContainsEquals(Graph::class, $classes);
		$this->assertClassExists(Smalldb::class);
		$this->assertNotContainsEquals(Smalldb::class, $classes);

		// A plain class
		$this->assertClassExists(PostRepository::class);
		$this->assertContainsEquals(PostRepository::class, $classes);

		// An abstract class
		$this->assertClassExists(Post::class);
		$this->assertContainsEquals(Post::class, $classes);

		// An interface
		$this->assertClassExists(CrudItem::class);
		$this->assertContainsEquals(CrudItem::class, $classes);

		// Excluded class
		$this->assertClassExists(SymfonyDemoDatabase::class);
		$this->assertNotContainsEquals(SymfonyDemoDatabase::class, $classes);

		// Check mapping
		$this->checkClassNameToFileName($locator, Graph::class);
		$this->checkClassNameToFileName($locator, ReflectionClass::class, false);
		$this->checkFileNameToClassName($locator, Graph::class);
	}


	public function testLocateBrokenClasses()
	{
		$locator = new Psr4ClassLocator(__NAMESPACE__, __DIR__, ["BadExample/BrokenClass"], []);
		$foundClasses = iterator_to_array($locator->getClasses());
		$foundClasses = array_values($foundClasses);

		// There should be at least one class that is not completely broken.
		$this->assertNotEmpty($foundClasses);

		// Classes that may be abstract but exists ...
		$this->assertContainsEquals(MissingImplementationDummy::class, $foundClasses);
		$this->assertContainsEquals(MissingTraitDummy::class, $foundClasses);
		$this->assertContainsEquals(MissingTypehintsDummy::class, $foundClasses);

		// ... and thus we can get a reflection object for each of them.
		$this->assertNotEmpty(new ReflectionClass(MissingImplementationDummy::class));
		$this->assertNotEmpty(new ReflectionClass(MissingTraitDummy::class));
		$this->assertNotEmpty(new ReflectionClass(MissingTypehintsDummy::class));

		// Classes that are really broken
		$this->assertNotContainsEquals(MissingInterfaceDummy::class, $foundClasses);
		$this->assertNotContainsEquals(MissingParentDummy::class, $foundClasses);
		// $this->assertNotContainsEquals(MissingTraitDummy::class, $foundClasses);
	}

}
