<?php
/*
 * Copyright (c) 2013, Josef Kufner  <jk@frozen-doe.net>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. Neither the name of the author nor the names of its contributors
 *    may be used to endorse or promote products derived from this software
 *    without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE REGENTS AND CONTRIBUTORS ``AS IS'' AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED.  IN NO EVENT SHALL THE REGENTS OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
 * OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
 * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
 * SUCH DAMAGE.
 */

namespace Smalldb\Cascade;

use Smalldb\Machine\AbstractMachine;

/**
 * Raw and ugly connector to access Smalldb interface from outter world.
 *
 * Deprecated! This connector will be replaced with something better soon.
 *
 * This connector also directly reads $_GET and $_POST, which is also ugly.
 * And to make it even worse, it produces output!
 */
class RawApiBlock extends BackendBlock
{

	protected $inputs = array(
		'id' => null,		// Entity ID
	);

	protected $outputs = array(
		'result' => true,
		'done' => true,
	);

	const force_exec = true;


	public function main()
	{
		$id = $this->in('id');

		if ($id === array() || $id == '') {
			$id = null;
		}


		$request_is_http_post = ($_SERVER['REQUEST_METHOD'] == 'POST');
		$action = @ $_GET['action'];

		try {
			$ref = $this->smalldb->ref($id);

			if ($request_is_http_post) {

				// Optionally accept action from POST
				if ($action === null) {
					$action = @ $_POST['action'];
				}

				// Get action arguments
				$args = @ $_POST['args_json'];
				if ($args !== null) {
					$args = json_decode($args, TRUE);
				}

				// Check if we have args
				if (!is_array($args)) {
					throw new \InvalidArgumentException('Arguments (args_json) not specified or invalid.');
				}

				// Invoke transition
				$result = $ref->__call((string) $action, (array) $args);

				if ($result instanceof \Smalldb\StateMachine\Reference) {
					$result = array(
						'id' => $result->id,
						'state' => $result->state,
					);
				}
			} else {

				$result = array(
					'id' => $ref->id,
					'properties' => $ref->properties,
					'state' => $ref->state,
				);

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

