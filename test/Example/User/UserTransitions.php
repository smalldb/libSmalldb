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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Smalldb\SmalldbBundle\Security\UserProxy;
use Smalldb\StateMachine\Test\Example\User\UserData\UserData;
use Smalldb\StateMachine\Test\Example\User\UserProfileData\UserProfileData;
use Smalldb\StateMachine\Transition\MethodTransitionsDecorator;
use Smalldb\StateMachine\Transition\TransitionDecorator;
use Smalldb\StateMachine\Transition\TransitionEvent;
use Smalldb\StateMachine\Transition\TransitionGuard;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;


class UserTransitions extends MethodTransitionsDecorator implements TransitionDecorator
{
	private Connection $db;
	private ?UserPasswordEncoderInterface $encoder;


	public function __construct(TransitionGuard $guard, Connection $db, ?UserPasswordEncoderInterface $encoder = null)
	{
		parent::__construct($guard);
		$this->db = $db;
		$this->encoder = $encoder;
	}


	/**
	 * @throws DBALException
	 */
	protected function register(TransitionEvent $transitionEvent, User $ref, UserData $data, string $plainPassword): void
	{
		if (!$this->encoder) {
			throw new \RuntimeException("Password encoder not available.");
		}

		$stmt = $this->db->prepare("
			INSERT INTO symfony_demo_user (id, username, password, roles, full_name, email)
			VALUES (:id, :username, :password, :roles, :fullName, :email)
		");

		$stmt->execute([
			'id' => $ref->getId(),
			'username' => $data->getUsername(),
			'password' => $this->encoder->encodePassword($ref, $plainPassword),
			'roles' => json_encode($data->getRoles()),
			'fullName' => $data->getFullName(),
			'email' => $data->getEmail(),
		]);
	}

	/**
	 * @throws DBALException
	 */
	protected function updateProfile(TransitionEvent $transitionEvent, User $ref, UserProfileData $data): void
	{
		$stmt = $this->db->prepare("
			UPDATE symfony_demo_user
			SET
				full_name = :fullName,
			    email = :email
			WHERE
				id = :id
		");

		$stmt->execute([
			'id' => $ref->getId(),
			'fullName' => $data->getFullName(),
			'email' => $data->getEmail(),
		]);
	}


	/**
	 * @throws DBALException
	 */
	protected function changePassword(TransitionEvent $transitionEvent, User $ref, string $newPassword): void
	{
		if (!$this->encoder) {
			throw new \RuntimeException("Password encoder not available.");
		}

		$stmt = $this->db->prepare("
			UPDATE symfony_demo_user
			SET
			    password = :password
			WHERE
				id = :id
		");

		$stmt->execute([
			'id' => $ref->getId(),
			'password' => $this->encoder->encodePassword($ref, $newPassword),
		]);
	}

}
