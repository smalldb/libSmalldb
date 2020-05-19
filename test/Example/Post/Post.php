<?php
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

namespace Smalldb\StateMachine\Test\Example\Post;

use Smalldb\StateMachine\Annotation\Access as Access;
use Smalldb\StateMachine\Annotation\Color;
use Smalldb\StateMachine\Annotation\State;
use Smalldb\StateMachine\Annotation\StateMachine;
use Smalldb\StateMachine\Annotation\Transition;
use Smalldb\StateMachine\Annotation\UseRepository;
use Smalldb\StateMachine\Annotation\UseTransitions;
use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\SqlExtension\Annotation\SQL;


/**
 * @StateMachine("post")
 * @UseRepository(PostRepository::class)
 * @UseTransitions(PostTransitions::class)
 * @Access\Policy("Author",
 *         @SQL\RequireOwner("authorId"),
 *         @Color("#4a0"))
 * @Access\Policy("Editor",
 *         @Access\RequireRole("ROLE_EDITOR"))
 * @Access\DefaultPolicy("Author")
 */
abstract class Post extends PostDataImmutable implements ReferenceInterface
{

	/**
	 * @State
	 * @Color("#def")
	 */
	const EXISTS = "Exists";

	/**
	 * @Transition("", {"Exists"})
	 * @Color("#4a0")
	 * @Access\AllowPolicy("Editor")
	 */
	abstract public function create(PostDataImmutable $itemData);

	/**
	 * @Transition("Exists", {"Exists"})
	 * @Access\AllowPolicy("Author")
	 */
	abstract public function update(PostDataImmutable $itemData);

	/**
	 * @Transition("Exists", {""})
	 * @Color("#a40")
	 * @Access\AllowPolicy("Author")
	 */
	abstract public function delete();

}

