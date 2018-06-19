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
 * IAuth implementation which allows everything (for testing only).
 */
class AllowAllAuth implements IAuth
{

	/// @copydoc Smalldb\StateMachine\Auth\IAuth::checkSession()
	public function checkSession()
	{
		// nop
	}


	/// @copydoc Smalldb\StateMachine\Auth\IAuth::getSessionMachine()
	public function getSessionMachine()
	{
		return null;
	}


	/// @copydoc Smalldb\StateMachine\Auth\IAuth::getUserId()
	public function getUserId()
	{
		return null;
	}


	/// @copydoc Smalldb\StateMachine\Auth\IAuth::hasUserRoles()
	public function hasUserRoles($roles)
	{
		return true;
	}

	/// @copydoc Smalldb\StateMachine\Auth\IAuth::isAllMighty()
	public function isAllMighty()
	{
		return true;
	}

}

