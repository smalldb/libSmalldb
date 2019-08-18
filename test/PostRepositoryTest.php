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

use PDO;
use PHPUnit\Framework\TestCase;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\Test\Example\Post\Post;
use Smalldb\StateMachine\Test\Example\Post\PostData;
use Smalldb\StateMachine\Test\Example\Post\PostDataImmutable;
use Smalldb\StateMachine\Test\Example\Post\PostRepository;
use Smalldb\StateMachine\Test\SmalldbFactory\SymfonyDemoContainer;


class PostRepositoryTest extends TestCase
{
	/** @var Smalldb */
	private $smalldb;

	/** @var PostRepository */
	private $postRepository;


	public function setUp(): void
	{
		$containerFactory = new SymfonyDemoContainer();
		$container = $containerFactory->createContainer();
		$this->postRepository = $container->get(PostRepository::class);
		$this->smalldb = $container->get(Smalldb::class);
	}


	public function testLoadState()
	{
		$ref = $this->smalldb->ref(Post::class, 1);

		$state = $this->postRepository->getState($ref);
		$this->assertEquals('Exists', $state);

		$state = $ref->getState();
		$this->assertEquals('Exists', $state);
	}


	public function testLoadData()
	{
		$ref = $this->smalldb->ref(Post::class, 1);

		/** @var PostData $data */
		$string = null;
		$data = $this->postRepository->getData($ref, $state);
		$this->assertEquals('Exists', $state);
		$this->assertEquals(1, $data->getId());
		$this->assertNotEmpty($data->getTitle());
	}

	public function testPostObjects()
	{
		$mutablePost = $this->createPostData();
		$immutablePost = new PostDataImmutable($mutablePost);
		$this->assertEquals(get_object_vars($mutablePost), get_object_vars($immutablePost));
	}


	public function testPostReferenceObjects()
	{
		/** @var Post $ref */
		$ref = $this->smalldb->ref(Post::class, 1);

		$postData = new PostDataImmutable($ref);

		$dataTitle = $postData->getTitle();
		$refTitle = $ref->getTitle();
		$this->assertEquals($refTitle, $dataTitle);
	}


	private function createPostData(): PostData
	{
		$postData = new PostData();
		$postData->setTitle('Foo');
		$postData->setSlug('foo');
		$postData->setAuthorId(1);
		$postData->setPublishedAt(new \DateTimeImmutable());
		$postData->setSummary('Foo, foo.');
		$postData->setContent('Foo. Foo. Foo.');
		return $postData;
	}

	public function testCreate()
	{
		/** @var Post $ref */
		$ref = $this->smalldb->ref(Post::class, 1000);
		$postData = $this->createPostData();

		/** @var PostData $data */
		$this->assertEquals('', $ref->getState());
		$ref->create($postData);
		$this->assertEquals('Exists', $ref->getState());
		$this->assertEquals([1000], $ref->getId());
		$this->assertEquals('Foo', $ref->getTitle());
	}


	public function testUpdate()
	{
		/** @var Post $ref */
		$ref = $this->smalldb->ref(Post::class, 1000);
		$ref->create($this->createPostData());
		$this->assertEquals('Exists', $ref->getState());

		$postData = $ref->getData();
		$this->assertEquals('Foo', $postData->getTitle());
		$postData->setTitle('Bar');

		$ref->update($postData);
		$this->assertEquals('Exists', $ref->getState());
		$this->assertEquals([1000], $ref->getId());
		$this->assertEquals('Bar', $ref->getTitle());
	}


	/**
	 * @depends testUpdate
	 */
	public function testDelete()
	{
		/** @var Post $ref */
		$ref = $this->smalldb->ref(Post::class, 1000);
		$ref->create($this->createPostData());
		$this->assertEquals('Exists', $ref->getState());

		$ref->delete();
		$this->assertEquals('', $ref->getState());
	}

}
