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


class PostDataImmutable
{
	/** @var int */
	protected $id;

	/** @var string */
	protected $title;

	/** @var string */
	protected $slug;

	/** @var string */
	protected $summary;

	/** @var string */
	protected $content;

	/** @var DateTimeImmutable */
	protected $publishedAt;

	/** @var int */
	protected $authorId;


	/**
	 * PostDataImmutable copy constructor.
	 */
	public function __construct($src = null)
	{
		if ($src !== null) {
			if (is_array($src)) {
				static::hydrate($this, $src);
			} else {
				$this->copyProperties($src);
			}
		}
	}

	protected function copyProperties(PostDataImmutable $src): void
	{
		$src->loadData();
		$this->id = $src->id;
		$this->title = $src->title;
		$this->slug = $src->slug;
		$this->summary = $src->summary;
		$this->content = $src->content;
		$this->publishedAt = $src->publishedAt;
		$this->authorId = $src->authorId;
	}

	protected function loadData(): void
	{
		// No-op.
	}


	/**
	 * @return int
	 *
	 * TODO: Fix return type conflict with ReferenceInterface::getId()
	 */
	public function getId()
	{
		return $this->id;
	}

	public function getContent(): string
	{
		return $this->content;
	}

	public function getPublishedAt(): DateTimeImmutable
	{
		return is_string($this->publishedAt) ? new DateTimeImmutable($this->publishedAt) : $this->publishedAt;
	}

	public function getTitle(): string
	{
		return $this->title;
	}

	public function getAuthorId(): int
	{
		return (int)$this->authorId;
	}

	public function getSlug(): string
	{
		return $this->slug;
	}

	public function getSummary(): string
	{
		return $this->summary;
	}

}
