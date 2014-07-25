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

use Smalldb\Machine\AbstractMachine;

/**
 * Show heading for given action on given state machine. Uses "heading" option
 * of the action.
 */
class HeadingBlock extends BackendBlock
{

	/**
	 * Block inputs
	 */
	protected $inputs = array(
		'ref' => null,		// Smalldb Reference
		'action' => null,	// Smalldb action
		'level' => 2,		// Heading level. (1 is page title, 2 is chapter, ...)
		'anchor' => null,	// Name of heading (name attribute).
		'slot' => 'default',
		'slot_weight' => 10,
	);

	/**
	 * Block outputs
	 */
	protected $outputs = array(
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
		$action = $this->in('action');
		$ref = $this->in('ref');
		$action_def = $ref->machine->describeMachineAction($action);

		if (isset($action_def['heading']) || (($action_def = $ref->machine->describeMachineAction('show')) && isset($action_def['heading']))) {
			$heading = template_format($action_def['heading'], $ref);
		} else if (!empty($action) && $action != 'listing') {
			if ($ref->id) {
				$heading = sprintf(_('%s: %s – %s'), $ref->machine_type, is_array($ref->id) ? join(' / ', $ref->id) : $ref->id, $action);
			} else {
				$heading = sprintf(_('%s – %s'), $ref->machine_type, $action);
			}
		} else {
			$heading = $ref->machine_type;
		}

		$this->templateAdd(null, 'core/header', array(
				'text'    => $heading,
				'img_src' => null,
				'link'    => null,
				'anchor'  => $this->in('anchor'),
				'level'   => $this->in('level'),
				'option'  => null,
			));
	}
}

