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


class PostData implements PostDataImmutable
{
	/** @var int */
	private $id;

	/** @var string */
	private $title;

	/** @var string */
	private $slug;

	/** @var string */
	private $summary;

	/** @var string */
	private $content;

	/** @var DateTimeImmutable */
	private $publishedAt;

	/** @var int */
	private $authorId;


	/**
	 * @return int
	 *
	 * TODO: Fix return type conflict with ReferenceInterface::getId()
	 */
	public function getId()
	{
		return $this->id;
	}

	public function setId(int $id): void
	{
		$this->id = $id;
	}

	public function getTitle(): string
	{
		return $this->title;
	}

	public function setTitle(string $title): void
	{
		$this->title = $title;
	}

	public function getSlug(): string
	{
		return $this->slug;
	}

	public function setSlug(string $slug): void
	{
		$this->slug = $slug;
	}

	public function getSummary(): string
	{
		return $this->summary;
	}

	public function setSummary(string $summary): void
	{
		$this->summary = $summary;
	}

	public function getContent(): string
	{
		return $this->content;
	}

	public function setContent(string $content): void
	{
		$this->content = $content;
	}

	public function getPublishedAt(): DateTimeImmutable
	{
		return is_string($this->publishedAt) ? new DateTimeImmutable($this->publishedAt) : $this->publishedAt;
	}

	public function setPublishedAt(DateTimeImmutable $publishedAt): void
	{
		$this->publishedAt = $publishedAt;
	}

	public function getAuthorId(): int
	{
		return (int) $this->authorId;
	}

	public function setAuthorId(int $authorId): void
	{
		$this->authorId = $authorId;
	}

}
