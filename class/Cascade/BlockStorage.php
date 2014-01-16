<?php
/*
 * Copyright (c) 2012, Josef Kufner  <jk@frozen-doe.net>
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

class BlockStorage implements \Cascade\Core\IBlockStorage
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


