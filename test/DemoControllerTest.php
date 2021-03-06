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

use DateTimeImmutable;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\SmalldbDefinitionBagInterface;
use Smalldb\StateMachine\Test\Example\Post\Post;
use Smalldb\StateMachine\Test\Example\Post\PostData\PostDataImmutable;
use Smalldb\StateMachine\Test\Example\Post\PostData\PostDataMutable;
use Smalldb\StateMachine\Test\Example\Post\PostRepository;
use Smalldb\StateMachine\Test\Example\Tag\Tag;
use Smalldb\StateMachine\Test\Example\Tag\TagData\TagDataMutable;
use Smalldb\StateMachine\Test\Example\Tag\TagRepository;
use Smalldb\StateMachine\Test\Example\User\UserRepository;
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
class DemoControllerTest extends TestCaseWithDemoContainer
{

	public function testContainer()
	{
		foreach ([Smalldb::class, PostRepository::class, TagRepository::class, UserRepository::class] as $service) {
			$this->assertInstanceOf($service, $this->get($service));
		}
	}


	public function testDefinitionBag()
	{
		/** @var SmalldbDefinitionBagInterface $definitionBag */
		$definitionBag = $this->get(SmalldbDefinitionBagInterface::class);

		foreach ($definitionBag->getAllMachineTypes() as $machineType) {
			$definition = $definitionBag->getDefinition($machineType);
			$this->assertInstanceOf(StateMachineDefinition::class, $definition);

			// Render the result
			$output = new TestOutputTemplate();
			$output->setTitle('Demo App: ' . ucfirst($machineType));
			$output->addStateMachineGraph($definition);
			$output->addStateMachineDefinitionDump($definition);
			$output->writeHtmlFile('demo_' . $machineType . '.html');
		}
	}


	/**
	 * Create a post in the repository.
	 */
	private function createPost(PostRepository $postRepository, int $id = 1000): Post
	{
		$post = $postRepository->ref($id);
		$this->assertInstanceOf(Post::class, $post);

		$postData = new PostDataMutable();
		$postData->setId($id);
		$postData->setTitle('Foo');
		$postData->setSlug('foo');
		$postData->setAuthorId(1);
		$postData->setPublishedAt(new DateTimeImmutable());
		$postData->setSummary('Foo, foo.');
		$postData->setContent('Foo. Foo. Foo.');
		$postData = new PostDataImmutable($postData);

		$post->create($postData);
		$this->assertEquals(Post::EXISTS, $post->getState());

		return $post;
	}


	public function testPostShowController()
	{
		$postRepository = $this->get(PostRepository::class);

		// The Post object is loaded by Symfony's argument resolver.
		$post = $this->createPost($postRepository);

		$this->postShowController($post);
	}

	private function postShowController(Post $post)
	{
		$this->render(['post' => $post]);
	}


	/**
	 * @dataProvider tagProvider
	 */
	public function testIndexController(?string $tagName)
	{
		/** @var PostRepository $posts */
		$posts = $this->get(PostRepository::class);
		/** @var TagRepository $tags */
		$tags = $this->get(TagRepository::class);

		$tagData = new TagDataMutable();
		$tagData->setName('Foo');

		$ref = $tags->ref(null);
		$ref->create($tagData);

		$this->indexController(1, $tagName, $posts, $tags);
	}

	private function indexController(?int $page, ?string $tagName, PostRepository $posts, TagRepository $tags)
	{
		/** @var Tag $tag */
		$tag = null;
		if ($tagName !== null) {
			$tag = $tags->findByName($tagName);
		}

		$latestPosts = $posts->findLatest($page, $tag);

		return $this->render(['posts' => $latestPosts]);
	}

	public function tagProvider()
	{
		yield 'no tag' => [null];
		yield 'voluptate' => ['voluptate'];
	}


	public function testEditController()
	{
		/** @var PostRepository $posts */
		$postRepository = $this->get(PostRepository::class);
		$post = $this->createPost($postRepository, 1000);

		$newTitle = 'A post about Bar.';
		$request = ['title' => $newTitle];

		$this->assertNotEquals($newTitle, $post->getTitle());

		$this->editController($request, $post);

		$this->assertEquals($newTitle, $post->getTitle());
	}

	private function editController($request, Post $post)
	{
		// TODO: Use real Symfony forms.

		$postData = new PostDataImmutable($post);

		// Process the form; update $postData from the $request.
		$postData = $postData->withTitle($request['title']);

		// if ($form->isSubmitted() && $form->isValid()) {
			// Do some additional modification specific to this form.
			$updatedPostData = $postData->withSlug($this->slugify($postData->getTitle()));
			$post->update($updatedPostData);
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
		// Read the 'title' attribute to emulate rendering.
		$this->assertNotEmpty($post->getTitle());
	}


	private function slugify(string $string)
	{
		return trim(preg_replace('/[^a-z0-9-]+/', '-', strtolower($string)), '-');
	}

}
