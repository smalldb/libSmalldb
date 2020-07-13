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

namespace Smalldb\StateMachine\Test\Example\Tag;

use Smalldb\StateMachine\SmalldbRepositoryInterface;
use Smalldb\StateMachine\Test\Misc\AbstractCountingSqlRepository;


class TagRepository extends AbstractCountingSqlRepository implements SmalldbRepositoryInterface
{
	protected const REF_CLASS = Tag::class;


	public function ref($id): Tag
	{
		/** @var Tag $ref */
		$ref = $this->getDataSource()->ref($id);
		return $ref;
	}


	public function findAll(): iterable
	{
		$q = $this->getDataSource()->createQueryBuilder()
			->addSelectFromStatements();
		$q->setMaxResults(1000);

		$result = $q->executeRef();
		$this->onQuery($q);

		return $result->fetchAll();
	}


	public function findByName(string $name): ?Tag
	{
		$q = $this->getDataSource()->createQueryBuilder()
			->addSelectFromStatements();
		$q->where('name = :name');
		$q->setMaxResults(1);

		$q->setParameter('name', $name);

		$result = $q->executeRef();
		$this->onQuery($q);

		/** @var Tag|null $tag */
		$tag = $result->fetch();
		return $tag;
	}


}
