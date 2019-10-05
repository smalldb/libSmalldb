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

namespace Smalldb\StateMachine\Test\SymfonyDemo\StateMachine;

use Smalldb\StateMachine\Annotation as SM;
use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\Test\SymfonyDemo\EntityInterface\PostImmutableInterface;
use Smalldb\StateMachine\Test\SymfonyDemo\SmalldbRepository\SmalldbPostRepository;


/**
 * Class PostRef
 *
 * @SM\StateMachine("doctrine-post")
 * @SM\UseRepository(SmalldbPostRepository::class)
 */
abstract class PostRef implements ReferenceInterface, PostImmutableInterface
{

	/**
	 * @SM\State
	 */
	const EXISTS = "Exists";

	abstract protected function getData(): ?PostImmutableInterface;


	public function getState(): string
	{
		$data = $this->getData();
		if ($data !== null) {
			return self::EXISTS;
		} else {
			return self::NOT_EXISTS;
		}
	}

}
