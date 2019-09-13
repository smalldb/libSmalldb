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


/**
 * Class PostData
 *
 * TODO: Write a generator to infer this class from PostDataImmutable.
 */
class PostData extends PostDataImmutable
{

	public function setId(?int $id): void
	{
		$this->id = $id;
	}

	public function setTitle(string $title): void
	{
		$this->title = $title;
	}

	public function setSlug(string $slug): void
	{
		$this->slug = $slug;
	}

	public function setSummary(string $summary): void
	{
		$this->summary = $summary;
	}

	public function setContent(string $content): void
	{
		$this->content = $content;
	}

	public function setPublishedAt(DateTimeImmutable $publishedAt): void
	{
		$this->publishedAt = $publishedAt;
	}

	public function setAuthorId(int $authorId): void
	{
		$this->authorId = $authorId;
	}

}
