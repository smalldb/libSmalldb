<?php
/*
 * Copyright (c) 2017, Josef Kufner  <josef@kufner.cz>
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

namespace Smalldb\StateMachine\Utils;


/**
 * Simple hook mechanism to dispatch events.
 *
 * Reason for using this simple mechanism rather than a big event
 * dispather/listener infrastructure is to minimize overhead, especially when
 * there are no listeners registered (the Reference class is used in way too
 * many instances).
 *
 * Tip: Lazy init a private hook in a public getter. Then check for null before
 * 	emitting the event.
 */
class Hook
{
	private $listeners = [];


	/**
	 * Add event listener
	 */
	public function addListener($callback)
	{
		$index = count($this->listeners);
		$this->listeners[] = $callback;
		return $index;
	}


	/**
	 * Remove event listener identified by the index returned from add().
	 */
	public function removeListener($index)
	{
		if (isset($this->listeners[$index])) {
			unset($index);
		} else {
			throw \InvalidArgumentException('Listener #' + $index + ' is not registered.');
		}
	}


	/**
	 * Get list of all registered listeners.
	 */
	public function getListeners()
	{
		return $this->listeners;
	}


	/**
	 * Call all registered callbacks when event happens.
	 */
	public function emit(...$args)
	{
		foreach ($this->listeners as $cb) {
			$cb(...$args);
		}
	}

}

