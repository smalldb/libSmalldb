<?php
/*
 * Copyright (c) 2019-2020, Josef Kufner  <josef@kufner.cz>
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

namespace Smalldb\StateMachine\Test\Example\Post;

use Smalldb\StateMachine\Test\Example\Comment\Comment;
use Smalldb\StateMachine\Test\Example\Comment\CommentRepository;
use Smalldb\StateMachine\Test\Example\Post\PostData\PostData;
use Smalldb\StateMachine\Test\Example\Post\PostData\PostDataImmutable;
use Smalldb\StateMachine\Test\Example\Tag\Tag;
use Smalldb\StateMachine\Test\Example\Tag\TagRepository;
use Smalldb\StateMachine\Test\Example\User\User;
use Smalldb\StateMachine\AccessControlExtension\Annotation\AC;
use Smalldb\StateMachine\Annotation\State;
use Smalldb\StateMachine\Annotation\StateMachine;
use Smalldb\StateMachine\Annotation\Transition;
use Smalldb\StateMachine\Annotation\UseRepository;
use Smalldb\StateMachine\Annotation\UseTransitions;
use Smalldb\StateMachine\DtoExtension\Annotation\WrapDTO;
use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\ReferenceProtectedAPI;
use Smalldb\StateMachine\SqlExtension\Annotation\SQL;
use Smalldb\StateMachine\SqlExtension\ReferenceDataSource\ReferenceQueryResult;
use Smalldb\StateMachine\StyleExtension\Annotation\Color;


/**
 * @StateMachine("post")
 * @UseRepository(PostRepository::class)
 * @UseTransitions(PostTransitions::class)
 * @WrapDTO(PostDataImmutable::class)
 * @AC\DefinePolicy("Author",
 *     @AC\AllOf(
 *         @AC\IsOwner("authorId"),
 *         @AC\HasRole("ROLE_EDITOR")
 *     ), @Color("#4a0"))
 * @AC\DefinePolicy("Editor",
 *     @AC\HasRole("ROLE_EDITOR"))
 * @AC\DefaultPolicy("Author")
 */
abstract class Post implements ReferenceInterface, PostData
{
	use ReferenceProtectedAPI;


	/**
	 * @State
	 * @Color("#def")
	 */
	const EXISTS = "Exists";


	/**
	 * @Transition("", {"Exists"})
	 * @Color("#4a0")
	 * @AC\UsePolicy("Editor")
	 */
	abstract public function create(PostDataImmutable $itemData);


	/**
	 * @Transition("Exists", {"Exists"})
	 * @AC\UsePolicy("Author")
	 */
	abstract public function update(PostDataImmutable $itemData);


	/**
	 * @Transition("Exists", {""})
	 * @Color("#a40")
	 * @AC\UsePolicy("Author")
	 */
	abstract public function delete();


	public function getAuthor(): User
	{
		/** @var User $user */
		$user = $this->getSmalldb()->ref('user', $this->getAuthorId());
		return $user;
	}


	public function getTags(): array
	{
		/** @var TagRepository $repository */
		$repository = $this->getSmalldb()->getRepository(Tag::class);
		return $repository->findByPost($this);
	}


	public function getComments(): ReferenceQueryResult
	{
		/** @var CommentRepository $repository */
		$repository = $this->getSmalldb()->getRepository(Comment::class);
		return $repository->findByPost($this);
	}

}

