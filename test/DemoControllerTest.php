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

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\Test\Example\Post\Post;
use Smalldb\StateMachine\Test\Example\Post\PostRepository;
use Smalldb\StateMachine\Test\Example\Tag\TagRepository;
use Smalldb\StateMachine\Test\Example\User\UserRepository;
use Smalldb\StateMachine\Test\SmalldbFactory\SymfonyDemoContainer;
use Symfony\Component\DependencyInjection\ContainerBuilder;


/**
 * DemoControllerTest -- try to emulate Symfony Demo app controllers
 * and test Smalldb as (almost) drop-in replacement of Doctrine. The point
 * of these tests is to make sure that the Smalldb API is similar enough
 * to well-established libraries and frameworks so that new developers
 * have as little trouble as possible.
 *
 * @see https://github.com/symfony/demo/blob/master/src/Controller/BlogController.php
 */
class DemoControllerTest extends TestCase
{

	private function createContainer(): ContainerInterface
	{
		$containerFactory = new SymfonyDemoContainer();
		return $containerFactory->createContainer();
	}

	public function testContainer()
	{
		$container = $this->createContainer();
		foreach ([Smalldb::class, PostRepository::class, TagRepository::class, UserRepository::class] as $service) {
			$this->assertInstanceOf($service, $container->get($service));
		}
	}

	public function testPostShowController()
	{
		$container = $this->createContainer();
		$postRepository = $container->get(PostRepository::class);

		$this->markTestIncomplete();

		// The Post object is loaded by Symfony's argument resolver.
		$post = $postRepository->ref(1);
		$this->assertInstanceOf(Post::class, $post);

		$this->postShowController($post);
	}

	private function postShowController(Post $post)
	{
		$this->render(['post' => $post]);
	}


	public function testIndexController()
	{
		$container = $this->createContainer();
		$posts = $container->get(PostRepository::class);
		$tags = $container->get(TagRepository::class);

		$this->indexController(0, 'tag', $posts, $tags);
	}

	private function indexController(?int $page, ?string $tagName, PostRepository $posts, TagRepository $tags)
	{
		$this->markTestIncomplete();

		$tag = null;
		if ($tagName !== null) {
			$tag = $tags->findOneBy(['name' => $tagName]);
		}

		$latestPosts = $posts->findLatest($page, $tag);

		return $this->render(['posts' => $latestPosts]);
	}

	/**
	 * A dummy render function to make examples a bit more realistic.
	 */
	private function render(array $templateArgs)
	{
		if (array_key_exists('posts', $templateArgs)) {
			$this->assertContainsOnlyInstancesOf(Post::class, $templateArgs['posts']);
		}

		if (array_key_exists('post', $templateArgs)) {
			$this->assertInstanceOf(Post::class, $templateArgs['post']);
		}
	}

}
