<?php declare(strict_types = 1);
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

use DateTimeImmutable;
use Smalldb\CodeCooker\Annotation\GenerateDTO;
use Smalldb\StateMachine\SqlExtension\Annotation\SQL;


/**
 * @SQL\Table("symfony_demo_post")
 * @SQL\StateSelect("'Exists'")
 * @GenerateDTO("PostData")
 */
class PostProperties
{

	/**
	 * @SQL\Id
	 */
	protected ?int $id = null;

	/**
	 * @SQL\Column
	 */
	protected string $title;

	/**
	 * @SQL\Column
	 */
	protected string $slug;

	/**
	 * @SQL\Column
	 */
	protected string $summary;

	/**
	 * @SQL\Column
	 */
	protected string $content;

	/**
	 * @SQL\Column("published_at")
	 */
	protected DateTimeImmutable $publishedAt;

	/**
	 * @SQL\Column("author_id")
	 */
	protected int $authorId;

	/**
	 * @SQL\Select("SELECT COUNT(*) FROM symfony_demo_comment WHERE symfony_demo_comment.post_id = this.id")
	 */
	protected ?int $commentCount = null;

}
