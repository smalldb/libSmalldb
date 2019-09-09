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

namespace Smalldb\StateMachine\Test\Example\Post;

use DateTimeImmutable;
use Smalldb\StateMachine\SqlExtension\Annotation as SQL;
use Smalldb\StateMachine\Utils\CopyConstructorTrait;


class PostDataImmutable
{
	use CopyConstructorTrait;

	/**
	 * @var int
	 * @SQL\Id
	 */
	protected $id;

	/**
	 * @var string
	 * @SQL\Column
	 */
	protected $title;

	/**
	 * @var string
	 * @SQL\Column
	 */
	protected $slug;

	/**
	 * @var string
	 * @SQL\Column
	 */
	protected $summary;

	/**
	 * @var string
	 * @SQL\Column
	 */
	protected $content;

	/**
	 * @var DateTimeImmutable
	 * @SQL\Column("published_at")
	 */
	protected $publishedAt;

	/**
	 * @var int
	 * @SQL\Column("author_id")
	 */
	protected $authorId;

	/**
	 * @var int|null
	 * @SQL\Select("SELECT COUNT(*) FROM symfony_demo_comment WHERE symfony_demo_comment.post_id = this.id")
	 */
	protected $commentCount = null;


	public function getId(): ?int
	{
		return $this->id;
	}

	/**
	 * Set/Update the ID. Used by ReferenceTrait.
	 */
	protected function setId(?int $id): void
	{
		$this->id = $id;
	}

	public function getContent(): string
	{
		return $this->content;
	}

	public function getPublishedAt(): ?DateTimeImmutable
	{
		return $this->publishedAt;
	}

	public function getTitle(): string
	{
		return $this->title;
	}

	public function getAuthorId(): int
	{
		return $this->authorId;
	}

	public function getSlug(): string
	{
		return $this->slug;
	}

	public function getSummary(): string
	{
		return $this->summary;
	}


	public function getCommentCount(): ?int
	{
		return $this->commentCount;
	}

}
