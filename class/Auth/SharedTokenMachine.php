<?php
/*
 * Copyright (c) 2016, Josef Kufner  <josef@kufner.cz>
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

namespace Smalldb\StateMachine\Auth;

/**
 * Session management using a shared token.
 *
 * Works well with CookieAuth authenticator. Implements secure login and
 * logout; leaves a space for everything else.
 *
 * This state machine requires two tables in SQL database: session and user.
 * The session table stores tokens, the user table is used to check login and
 * password pair.
 *
 * @see http://smalldb.org/security/
 *
 *
 * ### Usage example
 *
 * ```php
 * $smalldb = new JsonDirBackend(...);
 * $smalldb->setContext([ 'auth' => new CookieAuth([...], $smalldb), ... ]);
 * $auth = $smalldb->getAuth();
 *
 * $auth->getSessionMachine()->login($_POST['login'], $_POST['password']);
 *
 * $auth->getSessionMachine()->logout();
 * ```
 *
 *
 * ### Configuration Schema
 *
 * The state machine is configured using JSON object passed to the constructor
 * (the `$config` parameter). The object must match the following JSON schema
 * (the grey items are inherited; [JSON format](Auth/SharedTokenMachine.schema.json)):
 *
 * @htmlinclude doxygen/html/Auth/SharedTokenMachine.schema.html
 */
class SharedTokenMachine extends \Smalldb\StateMachine\FlupdoCrudMachine
{
	/**
	 * Map of table columns
	 */
	protected $table_columns = array(
		'session_id' => 'id',		// primary key
		'session_token' => 'token',	// not null
		'user_id' => 'user_id',		// foreign key to user table
	);

	/**
	 * User login listing
	 */
	protected $user_login_filters = array(
		'type' => 'user',
	);

	/**
	 * State machine property containing the login
	 */
	protected $user_login_property = 'email';

	/**
	 * State machine property containing the password hash
	 */
	protected $user_password_property = 'password';

	/**
	 * @copydoc FlupdoMachine::initializeMachine()
	 */
	protected function initializeMachine($config)
	{
		parent::initializeMachine($config);

		if (isset($config['table_columns'])) {
			$this->table_columns = array_replace_recursive($this->table_columns, $config['table_columns']);
		}
		if (isset($config['user_login_filters'])) {
			$this->user_login_filters = $config['user_login_filters'];
		}
		if (isset($config['user_login_property'])) {
			$this->user_login_property = $config['user_login_property'];
		}
		if (isset($config['user_password_property'])) {
			$this->user_password_property = $config['user_password_property'];
		}
	}


	/**
	 * Setup session machine
	 */
	protected function setupDefaultMachine($config)
	{

		// State function
		if ($this->state_select === null) {
			$this->state_select = '"Authenticated"'; // TODO
		}

		// Exists state only
		$this->states = [
			'Authenticated' => [
				'description' => _('User is authenticated and active.'),
				'color' => '#ddffcc',
			],
			'Prepared' => [
				'description' => _('Session token is waiting for user (i.e. to click login link sent via e-mail).'),
				'color' => '#ffffcc',
			],
			'Expired' => [
				'description' => _('Session has expired. Awaiting clean up.'),
				'color' => '#ffddcc',
			],
		];

		$this->access_policies = [
			'admin' => [
				'type' => 'nobody',
				'arrow_tail' => 'nonetee',
			],
			'owner' => [
				'type' => 'owner',
				'owner_property' => 'user_id',
				'arrow_tail' => 'dot',
			],
			'anonymous' => [
				'type' => 'anonymous',
				'arrow_tail' => 'odot',
			],
		];

		// Actions
		$this->actions = [
			'login' => [
				'description' => _('Login user using password.'),
				'color' => '#44aa00',
				'returns' => self::RETURNS_NEW_ID,
				'transitions' => [
					'' => [
						'targets' => [ '', 'Authenticated' ],
						'access_policy' => 'anonymous',
					],
				],
			],
			'useToken' => [
				'description' => _('Login user using prepared token'),
				'transitions' => [
					'Prepared' => [
						'targets' => [ 'Authenticated' ],
						'access_policy' => 'anonymous',
					],
				],
			],
			'logout' => [
				'description' => _('Logout user.'),
				'color' => '#aa4400',
				'transitions' => [
					'Authenticated' => [
						'targets' => [ '' ],
						'access_policy' => 'owner',
					],
					'Prepared' => [
						'targets' => [ '' ],
						'access_policy' => 'admin',
					],
					'Expired' => [
						'targets' => [ '' ],
						'access_policy' => 'owner',
					],
				],
			],
			'checkSession' => [
				'description' => _('Check whether the session is valid.'),
				'color' => '#aa88ff',
				'transitions' => [
					'' => [
						'targets' => [ '', 'Authenticated' ],
						'access_policy' => 'anonymous',
					],
				],
			],
			'prepare' => [
				'description' => _('Prepare session token for later use.'),
				'returns' => self::RETURNS_NEW_ID,
				'transitions' => [
					'' => [
						'targets' => [ 'Prepared' ],
					],
				],
			],
			'expire' => [
				'description' => _('Expire session. Timed transition.'),
				'color' => '#aaaaaa',
				'transitions' => [
					'Authenticated' => [
						'targets' => [ 'Expired' ],
					],
					'Prepared' => [
						'targets' => [ 'Expired' ],
					],
				],
			],
		];

		// Merge with config
		if (isset($config['actions'])) {
			$this->actions = array_replace_recursive($this->actions, $config['actions']);
		}
		if (isset($config['states'])) {
			$this->states = array_replace_recursive($this->states, $config['states']);
		}
		if (isset($config['access_policies'])) {
			$this->access_policies = array_replace_recursive($this->access_policies, $config['access_policies']);
		}
	}


	/**
	 * Login user
	 *
	 * @see Timing attack prevention:
	 * 	http://blog.ircmaxell.com/2014/11/its-all-about-time.html
	 */
	protected function login($ref, $user, $password)
	{
		// Get user
		$user_filters = $this->user_login_filters;
		$user_filters[$this->user_login_property] = $user;
		$user_list = $this->backend->createListing($user_filters)->fetchAll();
		if (empty($user_list)) {
			$user_ref = null;
		} else {
			$user_ref = reset($user_list);
		}

		// Check password
		$dummy_hash = password_hash((string) time(), PASSWORD_DEFAULT); // prevent timing attack to $user
		$passwd_hash = $user_ref ? $user_ref[$this->user_password_property] : $dummy_hash;
		$passwd_valid = $user_ref && password_verify($password, $passwd_hash);

		if (!$passwd_valid) {
			// One more protection against timing attack
			time_nanosleep(0, abs(crc32($user.$password.time().__FILE__) % 200000));
			return false;
		}

		// Login OK - generate session token
		$session_id = base64_encode(openssl_random_pseudo_bytes(12));
		$session_token = base64_encode(openssl_random_pseudo_bytes(24));

		// Store session token
		$n = $this->flupdo->insert()->into($this->flupdo->quoteIdent($this->table))
			->insert($this->flupdo->quoteIdent($this->table_columns['session_id']))
			->insert($this->flupdo->quoteIdent($this->table_columns['session_token']))
			->insert($this->flupdo->quoteIdent($this->table_columns['user_id']))
			->values([[$session_id, password_hash($session_token, PASSWORD_DEFAULT), $user_ref->id]])
			->exec();

		if ($n == 1) {
			return $session_id;
		} else {
			return false;
		}
	}


	/**
	 * Logout user - simply destroy session.
	 */
	protected function logout($ref)
	{
		return parent::delete($ref);
	}

}

