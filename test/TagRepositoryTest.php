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

use Smalldb\StateMachine\SqlExtension\Definition\SqlTableExtension;
use Smalldb\StateMachine\Test\Example\Tag\Tag;
use Smalldb\StateMachine\Test\Example\Tag\TagData\TagData;
use Smalldb\StateMachine\Test\Example\Tag\TagData\TagDataImmutable;
use Smalldb\StateMachine\Test\Example\Tag\TagData\TagDataMutable;
use Smalldb\StateMachine\Test\Example\Tag\TagRepository;


class TagRepositoryTest extends TestCaseWithDemoContainer
{
	private TagRepository $tagRepository;


	public function setUp(): void
	{
		parent::setUp();
		$this->tagRepository = $this->get(TagRepository::class);
	}


	public function testTagDefinitionSqlExtension()
	{
		$definition = $this->smalldb->getDefinition(Tag::class);
		$this->assertTrue($definition->hasExtension(SqlTableExtension::class), "SQL Extension is missing from the Tag definition!");

		/** @var SqlTableExtension $sqlExt */
		$sqlExt = $definition->getExtension(SqlTableExtension::class);
		$this->assertEquals("symfony_demo_tag", $sqlExt->getSqlTable());
	}


	public function testTagDto()
	{
		$tag = new TagDataMutable();
		$tag->setId(1);
		$tag->setName('Foo');

		$tagImmutable = new TagDataImmutable($tag);

		$this->assertEquals($tag->getId(), $tagImmutable->getId());
		$this->assertEquals($tag->getName(), $tagImmutable->getName());

		$bar = $tagImmutable->withNameFromSlug('bar-bar');
		$this->assertEquals('Bar bar', $bar->getName());

		$one = $bar->withResetName();
		$this->assertEquals('1', $one->getName());
	}


	public function testLoadState()
	{
		$ref = $this->smalldb->ref(Tag::class, 1);
		$state = $ref->getState();
		$this->assertEquals('Exists', $state);

		// One query to load the state
		$this->assertQueryCountEquals(1);
	}


	public function testLoadData()
	{
		/** @var Tag $ref */
		$ref = $this->smalldb->ref(Tag::class, 1);
		$this->assertEquals('Exists', $ref->getState());
		$this->assertEquals(1, $ref->getId());
		$this->assertNotEmpty($ref->getName());

		// One query to load the state, second to load data. One would be better.
		$this->assertQueryCountLessThanOrEqual(2);
	}

	public function testTagObjects()
	{
		$mutableTag = $this->createTagData();
		$immutableTag = new TagDataImmutable($mutableTag);
		$this->assertEquals(get_object_vars($mutableTag), get_object_vars($immutableTag));
	}


	public function testTagReferenceObjects()
	{
		/** @var Tag $ref */
		$ref = $this->smalldb->ref(Tag::class, 1);

		// FIXME: There should be no need to trigger loading manually.
		$ref->getName();

		$tagData = new TagDataImmutable($ref);

		$dataTitle = $tagData->getName();
		$refTitle = $ref->getName();
		$this->assertEquals($refTitle, $dataTitle);
	}


	private function createTagData(): TagData
	{
		$tagData = new TagDataMutable();
		$tagData->setName('Foo');
		return new TagDataImmutable($tagData);
	}

	public function testCreate()
	{
		/** @var Tag $ref */
		$ref = $this->smalldb->ref(Tag::class, 1000);
		$tagData = $this->createTagData();

		/** @var TagData $data */
		$this->assertEquals('', $ref->getState());
		$ref->create($tagData);
		$this->assertEquals('Exists', $ref->getState());
		$this->assertEquals(1000, $ref->getId());
		$this->assertEquals('Foo', $ref->getName());
	}


	/**
	 * @depends testCreate
	 */
	public function testUpdate()
	{
		/** @var Tag $ref */
		$ref = $this->smalldb->ref(Tag::class, 1000);
		$ref->create($this->createTagData());
		$this->assertEquals('Exists', $ref->getState());
		$this->assertEquals('Foo', $ref->getName());

		$tagData = new TagDataImmutable($ref);
		$tagData = $tagData->withName('Bar');
		$tagData = $tagData->withId(1001);

		$ref->update($tagData);
		$this->assertEquals('Exists', $ref->getState());
		$this->assertEquals(1001, $ref->getId());
		$this->assertEquals('Bar', $ref->getName());

		$oldRef = $this->smalldb->ref(Tag::class, 1000);
		$this->assertEquals('', $oldRef->getState(), 'The tag with the old ID should not exist.');
	}


	/**
	 * @depends testUpdate
	 */
	public function testDelete()
	{
		/** @var Tag $ref */
		$ref = $this->smalldb->ref(Tag::class, 1000);
		$ref->create($this->createTagData());
		$this->assertEquals('Exists', $ref->getState());

		$ref->delete();
		$this->assertEquals('', $ref->getState());
	}


	public function testFindAll()
	{
		$hasEmptyName = 0;
		$tagCount = 0;

		foreach ($this->tagRepository->findAll() as $tag) {
			$hasEmptyName |= empty($tag->getName());
			$tagCount++;
		}

		$this->assertEmpty($hasEmptyName, 'Some tag is missing its name.');
		$this->assertGreaterThanOrEqual(9, $tagCount, 'There should be at least 9 tags in the database.');

		// One query to load everything; data source should not query any additional data.
		$this->assertQueryCountEquals(1);
	}


	public function testFindByName()
	{
		$name = 'voluptate';  // Tag ID 7 in the test database
		$expectedTagId = 7;

		// Check test data that the slug exists
		$testRef = $this->tagRepository->ref($expectedTagId);
		$existingName = $testRef->getName();
		$this->assertEquals($name, $existingName);

		$this->assertQueryCountEquals(1);

		// Try to find
		$foundRef = $this->tagRepository->findByName($name);
		$this->assertInstanceOf(Tag::class, $foundRef);
		$this->assertEquals(Tag::EXISTS, $foundRef->getState());
		$this->assertEquals($name, $foundRef->getName());

		// Single query to both find the Tag and load the data.
		$this->assertQueryCountEquals(2);
	}

}
