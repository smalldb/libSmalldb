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

namespace Smalldb\StateMachine\Test\SymfonyDemo\SmalldbRepository;

use Smalldb\StateMachine\DoctrineExtension\AbstractDoctrineRepository;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\SmalldbRepositoryInterface;
use Smalldb\StateMachine\Test\SymfonyDemo\Entity\Post;
use Smalldb\StateMachine\Test\SymfonyDemo\Repository\PostRepository as DoctrinePostRepository;
use Smalldb\StateMachine\Test\SymfonyDemo\StateMachine\PostRef;


class SmalldbPostRepository extends AbstractDoctrineRepository implements SmalldbRepositoryInterface
{
	protected const REF_CLASS = PostRef::class;


	public function __construct(Smalldb $smalldb, DoctrinePostRepository $doctrineRepository)
	{
		parent::__construct($smalldb, $doctrineRepository);
	}


	/**
	 * Create a reference to a state machine identified by $id.
	 */
	public function ref($id): PostRef
	{
		/** @var PostRef $ref */
		$ref = parent::ref($id);
		return $ref;
	}


	/**
	 * @return Post[]
	 */
	public function findBySearchQuery(string $query, int $limit = Post::NUM_ITEMS): array
	{
		$refClass = $this->getReferenceClass();
		$result = $this->repository->findBySearchQuery($query, $limit);

		return array_map(function(Post $post) use ($refClass) {
			$ref = $this->ref($post->getId());
			($refClass)::hydrateWithDTO($ref, $post);
			return $ref;
		}, $result);
	}

}
