<?php
/*
 * Copyright (c) 2012, Josef Kufner  <jk@frozen-doe.net>
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

class BlockStorage implements \IBlockStorage
{
	protected $backend;
	protected $alias;

	/**
	 * Backend-related blocks. All of these should inherit from BackendBlock class.
	 */
	protected $backend_blocks = array(
		'init' => 'InitBlock',
		'show_diagram' => 'ShowDiagramBlock',
		'raw_api' => 'RawApiBlock',
	);

	/**
	 * Constructor will get options from core.ini.php file.
	 */
	public function __construct($storage_opts, $context)
	{
		$this->alias = $storage_opts['alias'];
		$this->backend = new $storage_opts['backend_class']($this->alias, $storage_opts);
	}


	/**
	 * Get Smalldb backend
	 */
	public function getSmalldbBackend()
	{
		return $this->backend;
	}


	/**
	 * Returns true if there is no way that this storage can modify or
	 * create blocks. When creating or modifying block, first storage that
	 * returns true will be used.
	 */
	public function isReadOnly()
	{
		return true;
	}


	/**
	 * Create instance of requested block and give it loaded configuration.
	 * No further initialisation here, that is job for cascade controller.
	 * Returns created instance or false.
	 */
	public function createBlockInstance ($block)
	{
		// Is backend prepared ?
		if ($this->backend === null) {
			throw new \RuntimeException('Backend is not initialized.');
		}

		$type = dirname($block);
		$action = basename($block);

		// Backend related blocks
		if ($type == $this->alias) {
			$c = @ $this->backend_blocks[$action];
			if ($c !== null) {
				$fc = __NAMESPACE__.'\\'.$c;
				return new $fc($this->backend);
			} else {
				return false;
			}
		}

		// Machine related blocks
		$machine = $this->backend->getMachine($type);

		// Ignore requests to other unknown types
		if ($machine === null) {
			return false;
		}

		// Get action description
		$action_desc = $machine->describeMachineAction($action);

		// Create block if action exists
		if ($action_desc !== null && isset($action_desc['block'])) {
			$block_class = @ $action_desc['block']['class'];
			if ($block_class !== null) {
				return new $block_class($machine, $action, $action_desc);
			} else {
				return new ActionBlock($machine, $action, $action_desc);
			}
		}

		return false;
	}


	/**
	 * Load block configuration. Returns false if block is not found.
	 */
	public function loadBlock ($block)
	{
		return false;
	}


	/**
	 * Store block configuration.
	 */
	public function storeBlock ($block, $config)
	{
		return false;
	}


	/**
	 * Delete block configuration.
	 */
	public function deleteBlock ($block)
	{
		return false;
	}


	/**
	 * Get time (unix timestamp) of last modification of the block.
	 */
	public function blockMTime ($block)
	{
		return 0;
	}


	/**
	 * List all available blocks in this storage.
	 */
	public function getKnownBlocks (& $blocks = array())
	{
		if ($this->backend === null) {
			throw new \RuntimeException('Backend is not initialized.');
		}

		// Backend related blocks
		foreach ($this->backend_blocks as $block => $class) {
			$blocks[$this->alias][] = $this->alias.'/'.$block;
		}

		// State machine related blocks
		foreach ($this->backend->getKnownTypes() as $type) {
			$machine = $this->backend->getMachine($type);
			$actions = $machine->getAllMachineActions('block');
			foreach ($actions as $a) {
				$blocks[$this->alias][] = $type.'/'.$a;
			}
		}

		return $blocks;
	}

}


