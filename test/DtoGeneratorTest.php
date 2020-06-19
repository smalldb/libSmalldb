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
use Smalldb\StateMachine\CodeCooker\Generator\DtoGenerator;
use Smalldb\StateMachine\Test\Example\Tag\TagData\TagData;
use Smalldb\StateMachine\Test\Example\Tag\TagData\TagDataImmutable;
use Smalldb\StateMachine\Test\Example\Tag\TagData\TagDataMutable;
use Smalldb\StateMachine\Test\Example\Tag\TagProperties;
use Smalldb\StateMachine\Test\Example\Tag\TagType;
use Symfony\Component\Form\Forms;


class DtoGeneratorTest extends TestCase
{

	public function testGenerateTagDto()
	{
		$g = new DtoGenerator();
		$generatedClasses = $g->generateDtoClasses(new ReflectionClass(TagProperties::class), 'TagData');

		$this->assertNotEmpty($generatedClasses);
		foreach ($generatedClasses as $generatedClass) {
			$this->assertClassOrInterfaceOrTraitExists($generatedClass);
		}

		$tag = new TagDataMutable();
		$tag->setId(1);
		$tag->setName('Foo Bar');

		$copiedTag = new TagDataImmutable($tag);
		$copiedTag2 = new TagDataMutable($copiedTag);

		$this->assertInstanceOf(TagData::class, $tag);
		$this->assertInstanceOf(TagData::class, $copiedTag);
		$this->assertInstanceOf(TagData::class, $copiedTag2);
		$this->assertEquals($tag, $copiedTag2);

		$slug = $copiedTag2->getSlug();
		$this->assertEquals('foo-bar', $slug);

		$mutatedTag = $copiedTag->withName('With Foo');
		$this->assertInstanceOf(TagData::class, $mutatedTag);
		$this->assertNotSame($copiedTag, $mutatedTag);
		$mutatedSlug = $mutatedTag->getSlug();
		$this->assertEquals('with-foo', $mutatedSlug);
	}


	/**
	 * @depends testGenerateTagDto
	 */
	public function testSimpleSymfonyFormWithNoInitialData()
	{
		$factory = Forms::createFormFactory();
		$form = $factory->create(TagType::class);
		$form->submit(['id' => 1, 'name' => 'New tag name']);

		$this->assertTrue($form->isSubmitted() && $form->isValid());
		$newTag = $form->getData();
		$this->assertInstanceOf(TagData::class, $newTag);

		$newName = $newTag->getName();
		$this->assertEquals('New tag name', $newName);
	}


	/**
	 * @depends testGenerateTagDto
	 */
	public function testSimpleSymfonyFormWithMutableEntity()
	{
		$tag = new TagDataMutable();
		$tag->setId(1);
		$tag->setName('Old tag name');

		$factory = Forms::createFormFactory();
		$form = $factory->create(TagType::class, $tag);
		$form->submit(['id' => 1, 'name' => 'New tag name']);

		$this->assertTrue($form->isSubmitted() && $form->isValid());
		$newTag = $form->getData();
		$this->assertInstanceOf(TagData::class, $newTag);

		$newName = $newTag->getName();
		$this->assertEquals('New tag name', $newName);
	}


	/**
	 * @depends testGenerateTagDto
	 */
	public function testSimpleSymfonyFormWithImmutableEntity()
	{
		$srcTag = new TagDataMutable();
		$srcTag->setId(1);
		$srcTag->setName('Old tag name');
		$tag = new TagDataImmutable($srcTag);

		$factory = Forms::createFormFactory();
		$form = $factory->create(TagType::class, $tag);
		$form->submit(['id' => 1, 'name' => 'New tag name']);

		$this->assertTrue($form->isSubmitted() && $form->isValid());

		$newTag = $form->getData();
		$this->assertInstanceOf(TagData::class, $newTag);

		$newName = $newTag->getName();
		$this->assertEquals('New tag name', $newName);
	}

}
