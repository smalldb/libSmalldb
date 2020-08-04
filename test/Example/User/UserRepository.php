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

namespace Smalldb\StateMachine\Test\Example\User;

use Smalldb\SmalldbBundle\Security\UserRepositoryInterface;
use Smalldb\StateMachine\SmalldbRepositoryInterface;
use Smalldb\StateMachine\SqlExtension\AbstractSqlRepository;
use Smalldb\StateMachine\SqlExtension\ReferenceDataSource\ReferenceQueryResult;


class UserRepository extends AbstractSqlRepository implements SmalldbRepositoryInterface, UserRepositoryInterface
{
	protected const REF_CLASS = User::class;


	public function ref($id): User
	{
		/** @var User $ref */
		$ref = $this->getDataSource()->ref($id);
		return $ref;
	}


	public function findByUserName(string $username): ?User
	{
		$q = $this->getDataSource()->createQueryBuilder()
			->addSelectFromStatements();
		$q->where('username = :username');
		$q->setMaxResults(1);

		$q->setParameter('username', $username);

		$result = $q->executeRef();

		/** @var User|null $ref */
		$ref = $result->fetch();
		return $ref;
	}


	public function findAll(?int $maxResults): ReferenceQueryResult
	{
		$q = $this->createQueryBuilder();
		$q->orderBy('id', 'DESC');
		if ($maxResults !== null) {
			$q->setMaxResults($maxResults);
		}
		return $q->executeRef();
	}

}

