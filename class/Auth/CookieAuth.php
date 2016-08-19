<?php
/*
 * Copyright (c) 2014-2016, Josef Kufner  <josef@kufner.cz>
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
use Smalldb\StateMachine\AbstractBackend;

/**
 * IAuth implementation using Smalldb state machine and shared session token 
 * in a cookie to identify SharedTokenMachine instance.
 *
 * This class handles cookies only, it is up to session machine to maintain session.
 *
 * To log in, use getSessionMachine() and invoke a login transition. To log
 * out, use a logout transition. The registered callback will take care of the
 * cookies in both cases.
 *
 * @warning Always use the same reference provided by getSessionMachine() to
 * 	manipulate the current session, otherwise the cookies will not be
 * 	updated, because other references does not have the
 * 	Reference::$after_transition_cb callback registered.
 *
 * @see http://smalldb.org/security/
 */
class CookieAuth implements \Smalldb\StateMachine\Auth\IAuth
{
	protected $smalldb;					///< Smalldb backend
	protected $session_machine;				///< Reference to session state machine

	/// @name Configuration
	/// @{
	protected $cookie_name = 'AuthToken';			///< Cookie name
	protected $cookie_ttl = 2592000;			///< Cookie duration [seconds] (default: 30 days)
	protected $user_id_property = 'user_id';		///< Name of the session machine property with user's ID
	protected $user_roles_property = 'user_roles';		///< Name of the session machine property with user's role
	protected $all_mighty_user_role = null;			///< Name of all mighty user role (admin)
	protected $all_mighty_cli = false;			///< Is command line all mighty?
	protected $session_machine_null_ref = 'session';	///< Null reference to session machine (array; use session_machine_ref_prefix if not set)
	protected $session_machine_ref_prefix = 'session';	///< Prefix of session machine reference (array; token ID will be appended)
	/// @}


	/**
	 * Constructor.
	 *
	 * @param $config Configuration options - see Configuration section.
	 * @param $smalldb Instance of %Smalldb backend (AbstractBackend).
	 */
	public function __construct($config, AbstractBackend $smalldb)
	{
		$this->smalldb = $smalldb;

		// Load configuration
		foreach (array('cookie_name', 'cookie_ttl', 'user_id_property', 'all_mighty_user_role', 'all_mighty_cli',
			'session_machine_null_ref', 'session_machine_ref_prefix') as $p)
		{
			if (isset($config[$p])) {
				$this->$p = $config[$p];
			}
		}

		// Check session machine name
		if (empty($this->session_machine_ref_prefix)) {
			throw new \InvalidArgumentException('Session machine not specified (see SharedTokenMachine).');
		}
		if (empty($this->session_machine_null_ref)) {
			$this->session_machine_null_ref = $this->session_machine_ref_prefix;
		}

		$this->session_machine = null;
	}


	/**
	 * Check session - read & update cookies, setup session state machine and register callbacks.
	 *
	 * This must be called before using any state machines. No transitions
	 * are invoked at this point, only the session state machine reference
	 * is created.
	 */
	public function checkSession()
	{
		// Get session token
		$session_token = @ $_COOKIE[$this->cookie_name];

		// Get reference to session state machine
		if ($session_token) {
			// Get session machine ref
			$session_id = (array) $this->session_machine_ref_prefix;
			$session_id[] = $session_token;
			$this->session_machine = $this->smalldb->ref($session_id);

			// Check session
			if ($this->session_machine->state == '') {
				// Invalid token
				$this->session_machine = $this->smalldb->ref($this->session_machine_null_ref);
			} else {
				// TODO: Validate token, not only session existence, and check whether session is in valid state (invoke a transition to check).

				// TODO: Session should tell how long it will last so the cookie should have the same duration.

				// Token is good - refresh cookie
				setcookie($this->cookie_name, $session_token, time() + $this->cookie_ttl, '/', null, !empty($_SERVER['HTTPS']), true);
			}
		} else {
			// No token
			$this->session_machine = $this->smalldb->ref($this->session_machine_null_ref);
		}

		// Update token on state change
		$t = $this;
		$this->session_machine->after_transition_cb[] = function($ref, $transition_name, $arguments, $return_value, $returns) use ($t) {
			if (!$ref->state) {
				// Remove cookie when session is terminated
				setcookie($t->cookie_name, null, time() + $t->cookie_ttl, '/', null, !empty($_SERVER['HTTPS']), true);
				if (isset($_SESSION)) {
					// Kill PHP session if in use
					session_regenerate_id(true);
				}
			} else {
				// Update cookie on state change
				setcookie($t->cookie_name, $ref->id, time() + $t->cookie_ttl, '/', null, !empty($_SERVER['HTTPS']), true);
			}
		};

		// Everything else is up to the state machine
	}


	/**
	 * Get session machine which manages all stuff around login and session.
	 */
	public function getSessionMachine()
	{
		if ($this->session_machine === null) {
			throw new RuntimeException('Session machine not ready ‒ '.__METHOD__.' called too soon.');
		}
		return $this->session_machine;
	}


	/// @copydoc Smalldb\StateMachine\Auth\IAuth::getUserId()
	public function getUserId()
	{
		if ($this->session_machine === null) {
			throw new RuntimeException('Session machine not ready ‒ '.__METHOD__.' called too soon.');
		}
		return $this->session_machine->state !== '' ? $this->session_machine[$this->user_id_property] : null;
	}


	/// @copydoc Smalldb\StateMachine\Auth\IAuth::hasUserRoles()
	public function hasUserRoles($roles)
	{
		if ($this->session_machine === null) {
			throw new RuntimeException('Session machine not ready ‒ '.__METHOD__.' called too soon.');
		}

		if (is_array($roles)) {
			return count(array_intersect($this->getUserRoles(), $roles)) > 0;
		} else {
			return in_array($roles, $this->getUserRoles());
		}
	}

	/// @copydoc Smalldb\StateMachine\Auth\IAuth::isAllMighty()
	public function isAllMighty()
	{
		return ($this->all_mighty_cli && empty($_SERVER['REMOTE_ADDR']) && php_sapi_name() == 'cli')
			|| ($this->all_mighty_user_role && $this->hasUserRoles($this->all_mighty_user_role));
	}


	/**
	 * Get list of user's roles, or null if not logged in.
	 *
	 * @note User's roles are typically not property of the session
	 * 	machine, but they can be calculated property of the machine.
	 * 	Therefore, there is no need to complicate this API with users.
	 */
	protected function getUserRoles()
	{
		if ($this->session_machine === null) {
			throw new RuntimeException('Session machine not ready ‒ '.__METHOD__.' called too soon.');
		}

		if ($this->session_machine->state == '') {
			return null;
		} else {
			return (array) $this->session_machine[$this->user_roles_property];
		}
	}

}

