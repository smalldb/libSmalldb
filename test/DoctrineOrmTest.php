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

use Psr\Container\ContainerInterface;
use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\Test\SmalldbFactory\SymfonyDemoOrmContainer;
use Smalldb\StateMachine\Test\SymfonyDemo\Entity\Post;
use Smalldb\StateMachine\Test\SymfonyDemo\Repository\PostRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Smalldb\StateMachine\Test\SymfonyDemo\SmalldbRepository\SmalldbPostRepository;
use Smalldb\StateMachine\Test\SymfonyDemo\StateMachine\PostRef;


class DoctrineOrmTest extends TestCase
{
	/** @var ContainerInterface */
	private $container;

	/** @var Smalldb */
	private $smalldb;

	public function setUp(): void
	{
		parent::setUp();
		$this->container = (new SymfonyDemoOrmContainer())->createContainer();
		$this->smalldb = $this->container->get(Smalldb::class);
	}


	public function testDoctrineInit()
	{
		$this->assertInstanceOf(ContainerInterface::class, $this->container);

		/** @var ManagerRegistry $reg */
		$reg = $this->container->get(ManagerRegistry::class);
		$this->assertInstanceOf(ManagerRegistry::class, $reg);

		$em = $reg->getManager();

		$postRepository = $em->getRepository(Post::class);
		$this->assertInstanceOf(PostRepository::class, $postRepository);

		$post = $postRepository->find(1);
		$this->assertInstanceOf(Post::class, $post);
		$this->assertNotEmpty($post->getId());
		$this->assertNotEmpty($post->getTitle());
	}


	public function testReferenceState()
	{
		$id = 1;

		$this->assertInstanceOf(Smalldb::class, $this->smalldb);

		$nullRef = $this->smalldb->nullRef(PostRef::class);
		$this->assertEquals(ReferenceInterface::NOT_EXISTS, $nullRef->getState());

		/** @var PostRef $ref */
		$ref = $this->smalldb->ref(PostRef::class, $id);
		$this->assertEquals(PostRef::EXISTS, $ref->getState());

		$this->assertEquals($id, $ref->getMachineId());
		$this->assertEquals($id, $ref->getId());

		$this->assertNotEmpty($ref->getTitle());
	}


	public function testReferenceFromRepository()
	{
		$id = 1;

		/** @var SmalldbPostRepository $repository */
		$repository = $this->container->get(SmalldbPostRepository::class);

		$ref = $repository->ref($id);
		$this->assertInstanceOf(PostRef::class, $ref);
		$this->assertEquals($id, $ref->getMachineId());
		$this->assertNotEmpty($ref->getTitle());
	}


	public function testReferenceProperties()
	{
		$this->assertInstanceOf(Smalldb::class, $this->smalldb);

		$definition = $this->smalldb->getDefinition(PostRef::class);

		$properties = $definition->getProperties();
		$this->assertNotEmpty($properties);
	}


	public function testFindLatest()
	{
		$queryString = 'de';

		/** @var SmalldbPostRepository $repository */
		$repository = $this->container->get(SmalldbPostRepository::class);

		$result = $repository->findBySearchQuery($queryString);

		$this->assertNotEmpty($result);
		$this->assertContainsOnlyInstancesOf(ReferenceInterface::class, $result);

		foreach ($result as $item) {
			$title = $item->getTitle();
			$this->assertStringContainsString($queryString, $title);
		}
	}

}
