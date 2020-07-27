<?php declare(strict_types = 1);
/*
 * Copyright (c) 2020, Josef Kufner  <josef@kufner.cz>
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

namespace Smalldb\StateMachine\Test\Example\Comment;

use Smalldb\StateMachine\Annotation\State;
use Smalldb\StateMachine\Annotation\StateMachine;
use Smalldb\StateMachine\Annotation\Transition;
use Smalldb\StateMachine\Annotation\UseRepository;
use Smalldb\StateMachine\Annotation\UseTransitions;
use Smalldb\StateMachine\DtoExtension\Annotation\WrapDTO;
use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\ReferenceProtectedAPI;
use Smalldb\StateMachine\StyleExtension\Annotation\Color;
use Smalldb\StateMachine\Test\Example\Comment\CommentData\CommentData;
use Smalldb\StateMachine\Test\Example\Comment\CommentData\CommentDataImmutable;
use Smalldb\StateMachine\Test\Example\Post\Post;
use Smalldb\StateMachine\Test\Example\User\User;


/**
 * @StateMachine("comment")
 * @WrapDTO(CommentDataImmutable::class)
 * @UseRepository(CommentRepository::class)
 * @UseTransitions(CommentTransitions::class)
 */
abstract class Comment implements ReferenceInterface, CommentData
{
	use ReferenceProtectedAPI;


	/**
	 * @State
	 * @Color("#def")
	 */
	const EXISTS = "Exists";


	/**
	 * @Transition("", {"Exists"})
	 * @Color("#a40")
	 */
	abstract public function create(CommentData $commentData);


	public function getPost(): Post
	{
		/** @var Post $ref */
		$ref = $this->getSmalldb()->ref(Post::class, $this->getPostId());
		return $ref;
	}


	public function getAuthor(): User
	{
		/** @var User $ref */
		$ref = $this->getSmalldb()->ref(User::class, $this->getAuthorId());
		return $ref;
	}

}
