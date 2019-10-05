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

namespace Smalldb\StateMachine\Test\SymfonyDemo\EntityInterface;

use Doctrine\Common\Collections\Collection;
use Smalldb\StateMachine\Test\SymfonyDemo\Entity\User;


interface PostImmutableInterface
{
	public function getId(): ?int;
	public function getTitle(): ?string;
	public function getSlug(): ?string;
	public function getContent(): ?string;
	public function getPublishedAt(): \DateTime;
	public function getAuthor(): ?User;
	public function getComments(): Collection;
	public function getSummary(): ?string;
	public function getTags(): Collection;
}
