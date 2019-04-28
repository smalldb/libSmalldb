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

/**
 * Authenticator interface
 *
 * State machine requires some basic informations about current user so it can
 * determine whether or not it is allowed to trigger a transition or expose
 * state machine state.
 */
interface IAuth
{

	/**
	 * Check session and setup whatever is neccessary (cookies and so).
	 *
	 * This shall be called when context is ready, but before application
	 * does anything.
	 */
	public function checkSession();


	/**
	 * Get session machine which manages all stuff around login and session.
	 */
	public function getSessionMachine();


	/**
	 * Get user's ID.
	 *
	 * User's ID is limited to single scalar value (any integer or string).
	 *
	 * @return User's ID, or NULL when nobody is logged in.
	 */
	public function getUserId();


	/**
	 * Check whether user has given role(s).
	 *
	 * Smalldb does not understand user roles, it can only check whether
	 * user's roles contain one of required values. User's roles are global
	 * and they are not related to any instance of anything.
	 *
	 * User may have any number of roles.
	 *
	 * @param $roles Name of required role (string), or array of required
	 * 	roles (array of strings).
	 * @return TRUE when user has at least one of requested roles, or FALSE
	 * 	otherwise.
	 */
	public function hasUserRoles($roles);


	/**
	 * Is user all mighty? (Admin or something like that.)
	 *
	 * When this function returns true, access control will be disabled.
	 */
	public function isAllMighty();

}

