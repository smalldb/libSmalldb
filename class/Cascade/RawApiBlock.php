<?php
/*
 * Copyright (c) 2013, Josef Kufner  <jk@frozen-doe.net>
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

use Smalldb\Machine\AbstractMachine;

/**
 * Raw and ugly connector to access %Smalldb interface from outter world.
 *
 * @deprecated
 *    This connector will be replaced with something better soon.
 *
 * @warning
 *    This connector directly reads $_GET and $_POST, which is ugly.  And to 
 *    make it even worse, it produces output!
 *
 *
 * ### HTTP API ###
 *
 * Following examples assume that this block is located at 
 * `http://example.com/smalldb-api/` and input `id` is connected to router's 
 * `path_tail`, so any additional path in uRL is interpreted as entity ID.
 *
 *
 * #### Retrieving state machine state and properties ####
 *
 *     HTTP GET: http://example.com/smalldb-api/state/machine/id
 *
 * State machine ID, state and properties are JSON encoded and sent back.
 *
 *
 * #### Browsing state machine space ####
 *
 *     HTTP GET: http://example.com/smalldb-api/?filter1=value1&filter2=value2&...
 *
 * Entire `$_GET` is passed as an only argument to StateMachine::AbstractBackend::createListing().
 *
 * Return value is JSON encoded and sent back.
 *
 *
 * #### Invoking a transition ####
 *
 *     HTTP POST: http://example.com/smalldb-api/state/machine/id
 *     DATA: action, args_json
 *
 * `action` is the action to be invoked, `args_json` contains JSON encoded list 
 * of arguments passed to StateMachine::Reference::__call().
 *
 * Return value is JSON encoded and sent back.
 *
 * @warning json_args is array of arguments! It must be always an array.
 *
 */
class RawApiBlock extends BackendBlock
{

	/**
	 * Block inputs
	 */
	protected $inputs = array(
		'id' => null,		// Entity ID
	);

	/**
	 * Block inputs
	 */
	protected $outputs = array(
		'result' => true,
		'done' => true,
	);

	/**
	 * Block must be always executed.
	 */
	const force_exec = true;


	/**
	 * Block body
	 */
	public function main()
	{
		$id = $this->in('id');

		if ($id === array() || $id == '') {
			$id = null;
		}

		$request_is_http_post = ($_SERVER['REQUEST_METHOD'] == 'POST');

		try {
			if ($request_is_http_post) {

				// Optionally accept action from POST
				if ($action === null) {
					$action = @ $_POST['action'];
				}

				// Get action arguments
				$args = @ $_POST['args_json'];
				if (is_string($args)) {
					$args = json_decode($args, TRUE);
				}

				// Check if we have args
				if (!is_array($args)) {
					throw new \InvalidArgumentException('Arguments (args_json) not specified or invalid.');
				}

				// Invoke transition
				$ref = $this->smalldb->ref($id);
				$result = $ref->__call((string) $action, (array) $args);

				if ($result instanceof \Smalldb\StateMachine\Reference) {
					$result = array(
						'id' => $result->id,
						'state' => $result->state,
					);
				} else {
					$result = array(
						'id' => $ref->id,
						'state' => $ref->state,
						'result' => $result,
					);
				}
			} else {
				if ($id === null) {
					$result = $this->smalldb->createListing($_GET);
				} else {
					$ref = $this->smalldb->ref($id);
					$action = @ $_GET['action'];
					$result = array(
						'id' => $ref->id,
						'properties' => $ref->properties,
						'state' => $ref->state,
					);

				}
			}
		}
		catch (\Smalldb\StateMachine\InvalidReferenceException $ex) {
			// Unknown machine type
			$result = null;
		}
		catch (\Exception $ex) {
			$this->templateOptionSet('root', 'http_status_code', 500);
			$result = array(
				'exception' => get_class($ex),
				'message' => $ex->getMessage(),
				'code' => $ex->getCode(),
			);
		}

		$this->templateAddToSlot(null, 'root', 1, 'core/print_r', array(
				'data' => $result,
			));
	}

}

