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
 * todo: DOC!
 * Raw and ugly connector to access %Smalldb interface from outter world.
 *
 * Deprecated! This connector will be replaced with something better soon.
 *
 * This connector also directly reads $_GET and $_POST, which is also ugly.
 * And to make it even worse, it produces output!
 *
 * If route is matched, inputs are copied to route and processed using 
 * filename_format funciton. Matched route and following data are available as 
 * variables:
 *
 *   - `{smalldb_type}`: State machine type
 *   - `{smalldb_action}`: Action (transition name)
 *   - `{smalldb_action_or_show}`: "show" or "listing" (for null refs) is used when action is empty.
 *
 * @note If input is set to "{smalldb_ref}", then it is set to 
 * Smalldb\StateMachine\Reference object instead of string.
 *
 * @par Example
 * 	If input "block" is set to "{smalldb_type}/{smalldb_action_or_show}",
 * 	then output 'block' will be usable as input for block_loader.
 */
class RouterFactoryBlock extends BackendBlock
{

	/**
	 * Block inputs
	 */
	protected $inputs = array(
		'*' => null,
	);

	/**
	 * Block inputs
	 */
	protected $outputs = array(
		'postproc' => true,
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
		$this->out('postproc', array($this, 'postprocessor'));
		$this->out('done', true);
	}


	/**
	 * Route postprocessor to be registered in router. Route will call this 
	 * method to check whether a %Smalldb route is valid reference to some 
	 * statemachine. If valid reference is found, the reference is 
	 * published at router's output.
	 *
	 * Postprocessor uses '!' to denote action. Action is part of path 
	 * after the last '!'. Action never contains slash and it must be at 
	 * the end of the path.
	 */
	public function postprocessor($route)
	{
		try {
			$args = $route;

			// Get action
			$action = isset($route['path_action']) ? $route['path_action'] : null;

			// Try numeric keys in route
			$id = array();
			for ($i = 0; isset($route[$i]); $i++) {
				$id[] = $route[$i];
			}

			// If it failed, try path_tail
			if (empty($id)) {
				$path = $route['path_tail'];
				if (empty($path)) {
					return false;
				}
				$id = $this->convertPathToMachineId($path);
			}

			// Create reference to state machine
			$ref = $this->smalldb->ref($id);
			$args['smalldb_ref'] = $ref;
			$args['smalldb_type'] = $ref->machineType;
			$args['smalldb_action'] = $action === null ? '' : $action;

			// Default action to make life easier
			if ($action === null) {
				// Check whether ref is null
				if ($ref->isNullRef()) {
					// Null ref means we want listing
					$args['smalldb_action_or_show'] = 'listing';
				} else {
					$args['smalldb_action_or_show'] = 'show';
				}
			} else {
				$args['smalldb_action_or_show'] = $action;
			}

			// Copy inputs to outputs
			foreach ($this->inAll() as $in => $val) {
				if ($val === '{smalldb_ref}') {
					$route[$in] = $ref;
				} else {
					$route[$in] = filename_format($val, $args);
				}
			}

			return $route;
		}
		catch (\Smalldb\StateMachine\InvalidReferenceException $ex) {
			// Ref is not valid => route does not exist.
			return false;
		}
	}


	private function convertPathToMachineId($path)
	{
		if ($path[0] == 'firma' && $path[2] == 'produkt') {
			return array('market_item', $path[1], $path[3]);
		}

		return $path;
	}

}

