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

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Smalldb\StateMachine\Test\SmalldbFactory\SymfonyDemoOrmContainer;
use Smalldb\StateMachine\Test\SymfonyDemo\Entity\Post;
use Smalldb\StateMachine\Test\SymfonyDemo\Repository\PostRepository;
use Doctrine\Common\Persistence\ManagerRegistry;


class DoctrineOrmTest extends TestCase
{
	/** @var ContainerInterface */
	private $container;


	public function setUp(): void
	{
		parent::setUp();
		$this->container = (new SymfonyDemoOrmContainer())->createContainer();
	}


	public function testInit()
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

}
