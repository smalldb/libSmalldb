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
use Smalldb\StateMachine\SqlExtension\AbstractSqlRepository;
use Smalldb\StateMachine\SqlExtension\ReferenceDataSource\ReferenceQueryResult;
use Smalldb\StateMachine\Test\Example\Post\Post;


class TagRepository extends AbstractSqlRepository implements SmalldbRepositoryInterface
{
	protected const REF_CLASS = Tag::class;


	public function ref($id): Tag
	{
		/** @var Tag $ref */
		$ref = $this->getDataSource()->ref($id);
		return $ref;
	}


	public function findAll(): ReferenceQueryResult
	{
		$q = $this->getDataSource()->createQueryBuilder()
			->addSelectFromStatements();
		$q->setMaxResults(1000);

		return $q->executeRef();
	}


	public function findByName(string $name): ?Tag
	{
		$q = $this->getDataSource()->createQueryBuilder()
			->addSelectFromStatements();
		$q->where('name = :name');
		$q->setMaxResults(1);

		$q->setParameter('name', $name);

		$result = $q->executeRef();

		/** @var Tag|null $tag */
		$tag = $result->fetch();
		return $tag;
	}


	/**
	 * @param string[] $names
	 */
	public function findByNames(array $names): ReferenceQueryResult
	{
		$q = $this->getDataSource()->createQueryBuilder()
			->addSelectFromStatements();
		$q->where('name IN (:names)');

		$q->setParameter('names', $names, \Doctrine\DBAL\Connection::PARAM_STR_ARRAY);

		return $q->executeRef();
	}


	public function findByPost(Post $post): ReferenceQueryResult
	{
		$q = $this->getDataSource()->createQueryBuilder()
			->addSelectFromStatements()
			->join('this', 'symfony_demo_post_tag', 'pt', 'this.id = pt.tag_id')
			->andWhere('pt.post_id = :post_id');

		$q->setParameter('post_id', $post->getId());

		return $q->executeRef();
	}

}
