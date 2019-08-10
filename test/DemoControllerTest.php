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
use Psr\Container\ContainerInterface;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\SmalldbDefinitionBagInterface;
use Smalldb\StateMachine\Test\Database\SymfonyDemoDatabase;
use Smalldb\StateMachine\Test\Example\Post\Post;
use Smalldb\StateMachine\Test\Example\Post\PostData;
use Smalldb\StateMachine\Test\Example\Post\PostRepository;
use Smalldb\StateMachine\Test\Example\Tag\Tag;
use Smalldb\StateMachine\Test\Example\Tag\TagRepository;
use Smalldb\StateMachine\Test\Example\User\UserRepository;
use Smalldb\StateMachine\Test\SmalldbFactory\SymfonyDemoContainer;
use Smalldb\StateMachine\Test\TestTemplate\TestOutput;
use Smalldb\StateMachine\Test\TestTemplate\TestOutputTemplate;

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
	/** @var PDO */
	protected $db;


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


	public function testSymfonyDemoDatabase()
	{
		/** @var PDO $db */
		$db = $this->createContainer()->get(SymfonyDemoDatabase::class);

		// Check that the database is ready
		$stmt = $db->query('select count(*) from symfony_demo_post');
		$maxPostId = $stmt->fetchColumn(0);
		$this->assertGreaterThan(0, $maxPostId, "No posts found in the database.");
	}


	public function testDefinitionBag()
	{
		$container = $this->createContainer();
		/** @var SmalldbDefinitionBagInterface $definitionBag */
		$definitionBag = $container->get(SmalldbDefinitionBagInterface::class);

		foreach ($definitionBag->getAllMachineTypes() as $machineType) {
			$definition = $definitionBag->getDefinition($machineType);
			$this->assertInstanceOf(StateMachineDefinition::class, $definition);

			// Render the result
			$output = new TestOutputTemplate();
			$output->setTitle('Demo App: ' . ucfirst($machineType));
			$output->addStateMachineGraph($definition);
			$output->writeHtmlFile('demo_' . $machineType . '.html');
		}
	}


	/**
	 * Create a post in the repository.
	 */
	private function createPost(PostRepository $postRepository, int $id = 1): Post
	{
		/** @var Post $post */
		$post = $postRepository->ref($id);
		$this->assertInstanceOf(Post::class, $post);

		$post->create(new PostData(['id' => $id, 'title' => 'A Post about Foo #' . $id]));
		$this->assertEquals(Post::EXISTS, $post->getState());

		return $post;
	}


	public function testPostShowController()
	{
		$container = $this->createContainer();
		$postRepository = $container->get(PostRepository::class);

		// The Post object is loaded by Symfony's argument resolver.
		$post = $this->createPost($postRepository);

		$this->postShowController($post);
	}

	private function postShowController(Post $post)
	{
		$this->render(['post' => $post]);
	}


	public function testIndexController()
	{
		$container = $this->createContainer();
		/** @var PostRepository $posts */
		$posts = $container->get(PostRepository::class);
		/** @var TagRepository $tags */
		$tags = $container->get(TagRepository::class);

		/** @var Tag $ref */
		$ref = $tags->ref(null);
		$ref->create(new PostData(['id' => 1, 'title' => 'foo']));

		$this->indexController(0, 'foo', $posts, $tags);
	}

	private function indexController(?int $page, ?string $tagName, PostRepository $posts, TagRepository $tags)
	{
		$tag = null;
		if ($tagName !== null) {
			$this->markTestIncomplete(); // TODO
			$tag = $tags->findOneBy(['name' => $tagName]);
		}

		$latestPosts = $posts->findLatest($page, $tag);

		return $this->render(['posts' => $latestPosts]);
	}


	public function testEditController()
	{
		$container = $this->createContainer();
		/** @var PostRepository $posts */
		$postRepository = $container->get(PostRepository::class);
		$post = $this->createPost($postRepository);

		$newTitle = 'A post about Bar.';
		$request = ['title' => $newTitle];

		$this->assertNotEquals($newTitle, $post->getTitle());

		$this->editController($request, $post);

		$this->assertEquals($newTitle, $post->getTitle());
	}

	private function editController($request, Post $post)
	{
		$postData = $post->getData();

		// Process the form; update $postData from the $request.
		$postData->setTitle($request['title']);

		// if ($form->isSubmitted() && $form->isValid()) {
			$postData->setSlug($this->slugify($postData->getTitle()));
			$post->update($postData);
		// }

		return $this->render([
			'post' => $post,
			//'form' => $form->createView(),
		]);
	}


	/**
	 * A dummy render function to make examples a bit more realistic.
	 */
	private function render(array $templateArgs)
	{
		if (array_key_exists('posts', $templateArgs)) {
			$this->assertContainsOnlyInstancesOf(Post::class, $templateArgs['posts']);
			foreach ($templateArgs['posts'] as $post) {
				$this->renderPost($post);
			}
		}

		if (array_key_exists('post', $templateArgs)) {
			$this->assertInstanceOf(Post::class, $templateArgs['post']);
			$this->renderPost($templateArgs['post']);
		}
	}


	private function renderPost(Post $post)
	{
		// Read the 'name' attribute to emulate rendering.
		$postData = $post->getData();
		$this->assertNotEmpty($postData->getTitle());
	}


	private function slugify(string $string)
	{
		return trim(preg_replace('/[^a-z0-9-]+/', '-', strtolower($string)), '-');
	}

}
