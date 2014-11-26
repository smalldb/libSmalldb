<?php
/*
 * Copyright (c) 2014, Josef Kufner  <jk@frozen-doe.net>
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

namespace Smalldb\Cascade;

/**
 * IAuth implementation using %Smalldb state machine and simple session token 
 * in cookie.
 *
 * Configuration options (all mandatory):
 *   - `smalldb`: Instance of Smalldb\StateMachine\AbstractBackend.
 *   - `machine_ref_prefix`: Prefix of machine ID (array).
 *   - `machine_null_ref`: State machine type for null ref.
 *   - `cookie_name`: Name of the cookie for a token. (default: 'auth')
 *   - `cookie_ttl`: How long cookie is valid. (default: 10 years)
 *   - `session_ttl`: Duration of the session (should be greater or equal to `cookie_ttl`, default: 10 years).
 *   - `user_id_property`: Name of the session machine property with user's ID.
 *   - `user_role_property`: Name of the session machine property with user's role. You may want to have this property calculated.
 */
class Auth implements \Smalldb\StateMachine\IAuth, \Cascade\Core\IAuth
{
	protected $smalldb;				///< Smalldb backend
	protected $session_machine;			///< Reference to session state machine
	protected $cookie_name = 'AuthToken';		///< Cookie name
	protected $cookie_ttl = 315569260;		///< Cookie duration [seconds]
	protected $session_ttl = 315569260;		///< Session duration [seconds]
	protected $user_id_property = 'user_id';	///< Name of the session machine property with user's ID
	protected $user_role_property = 'user_role';	///< Name of the session machine property with user's role
	protected $logout_transition = 'logout';	///< Name of the logout transition


	/**
	 * Constructor.
	 */
	public function __construct($config)
	{
		$this->smalldb = $config['smalldb'];

		if (isset($config['cookie_name'])) {
			$this->cookie_name = $config['cookie_name'];
		}
		if (isset($config['cookie_ttl'])) {
			$this->cookie_ttl  = $config['cookie_ttl'];
		}
		if (isset($config['session_ttl'])) {
			$this->session_ttl = $config['session_ttl'];
		}
		if (isset($config['user_id_property'])) {
			$this->user_id_property = $config['user_id_property'];
		}
		if (isset($config['logout_transition'])) {
			$this->logout_transition = $config['logout_transition'];
		}

		// Get session token
		$session_token = @ $_COOKIE[$this->cookie_name];

		// Get reference to session state machine
		if ($session_token) {
			$session_id = $config['machine_ref_prefix'];
			$session_id[] = $session_token;
			$this->session_machine = $this->smalldb->ref($session_id);
			if ($this->session_machine->state == '') {
				// Invalid token
				$this->session_machine = $this->smalldb->ref($config['machine_null_ref']);
			}
		} else {
			$this->session_machine = $this->smalldb->ref($config['machine_null_ref']);
		}

		// Refresh cookie with token
		if ($session_token != null) {
			setcookie($this->cookie_name, $session_token, time() + $this->cookie_ttl, '/', null, !empty($_SERVER['HTTPS']), true);
		}

		// Update cookie when token changes
		$t = $this;
		$this->session_machine->pk_changed_cb[] = function($ref, $new_pk) use ($t) {
			if (is_array($new_pk)) {
				$new_pk = $new_pk[0];
			}
			setcookie($t->cookie_name, $new_pk, time() + $t->cookie_ttl, '/', null, !empty($_SERVER['HTTPS']), true);
		};

		// Remove token when user logs out
		$this->session_machine->after_transition_cb[] = function($ref, $transition_name, $arguments, $return_value, $returns) use ($t) {
			if ($transition_name == $t->logout_transition) {
				setcookie($t->cookie_name, null, time() + $t->cookie_ttl, '/', null, !empty($_SERVER['HTTPS']), true);
				session_unset();	// Do not destroy session, only clear all data, so logout message can be stored in session.
			}
		};

		//debug_dump($this->session_machine->state, 'Session state');

		// Everything else is up to the state machine
	}


	/**
	 * Get session machine which manages all stuff around login and session.
	 */
	public function getSessionMachine()
	{
		return $this->session_machine;
	}


	/// @copydoc Smalldb\StateMachine\IAuth::getUserId()
	public function getUserId()
	{
		return $this->session_machine->state !== '' ? $this->session_machine[$this->user_id_property] : null;
	}


	/// @copydoc Smalldb\StateMachine\IAuth::getUserRole()
	public function getUserRole()
	{
		return $this->session_machine->id !== null ? $this->session_machine[$this->user_role_property] : null;
	}


	/**
	 * First level of authorization.
	 *
	 * @return Returns true if user is allowed to use the block.
	 */
	public function isBlockAllowed($block_name, & $details = null)
	{
		return true;
	}

}

