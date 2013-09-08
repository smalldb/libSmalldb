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
 * Universal implemntation of state machine action invocation. Inputs are
 * passed as arguments to the transition, returned value is set on one or more
 * outputs.
 */
class ActionBlock extends \Block
{

	protected $inputs = array(
		'*' => null,
	);

	protected $outputs = array(
		'*' => true,
		'done' => true,
	);

	const force_exec = true;

	protected $machine;
	protected $action;
	protected $output_values;

	/**
	 * Setup block to act as expected. Configuration is done by Smalldb
	 * Block Storage.
	 */
	public function __construct($machine, $action, $action_desc)
	{
		$this->machine = $machine;
		$this->action = $action;

		// get block description (block is not created unless this is defined)
		$block_desc = $action_desc['block'];

		// define inputs
		if (!is_array($block_desc['inputs'])) {
			throw new \RuntimeException('Inputs are not specified in block configuration.');
		}
		$this->inputs = $block_desc['inputs'];

		// define outputs
		if (!is_array($block_desc['outputs'])) {
			throw new \RuntimeException('Outputs are not specified in block configuration.');
		}
		$this->output_values = $block_desc['outputs'];
		$this->outputs = array_combine(array_keys($this->output_values), array_pad(array(), count($this->output_values), true));
		$this->outputs['done'] = true;
	}


	public function main()
	{
		$args = $this->inAll();

		// get ID if specified
		if (array_key_exists('id', $args)) {
			$id = $args['id'];
			unset($args['id']);
		} else {
			$id = null;
		}

		// invoke transition
		// TODO: Handle exceptions
		$result = $this->machine->invokeTransition($id, $action, $args, $returns);

		// interpret return value
		switch ($returns) {
			case AbstractMachine::RETURNS_VALUE:
				break;
			case AbstractMachine::RETURNS_NEW_ID:
				$id = $result;
			default:
				throw new \RuntimeException('Unknown semantics of the return value: '.$returns);
		}

		// set outputs
		foreach ($this->output_values as $output => $out_value) {
			switch ($out_value) {
				case 'id':
					$this->out($output, $id);
					break;
				case 'return_value':
					$this->out($output, $result);
					break;
				case 'properties':
					$this->out($output, $this->machine->getProperties($id));
					break;
				case 'state':
					$this->out($output, $this->machine->getState($id));
					break;
			}
		}

		$this->out('done', true);
	}

}

