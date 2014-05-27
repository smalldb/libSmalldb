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

/**
 * Block which displays state diagram of given state machine.
 */
class ShowDiagramBlock extends BackendBlock
{

	/**
	 * Block inputs
	 */
	protected $inputs = array(
		'machine_type' => null,
		'gv_config' => null,
		'gv_profile' => 'smalldb',
		'slot' => 'default',
		'slot_weight' => 50,
	);

	/**
	 * Default input connections.
	 */
	protected $connections = array(
		'machine_type' => array(),
		'gv_config' => array('config', 'core.graphviz'),
	);

	/**
	 * Block inputs
	 */
	protected $outputs = array(
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
		$type = $this->in('machine_type');
		$config = $this->in('gv_config');
		$profile = $this->in('gv_profile');

		if (!isset($config[$profile])) {
			error_log('Unknown graphviz renderer profile: '.$profile);
			return;
		}

		$machine = $this->smalldb->getMachine($type);
		if ($machine === null) {
			error_log('Unknown state machine type: '.$type);
			return;
		}

		$dot = $machine->exportDot();
		$hash = md5($dot);

		$dot_file = filename_format($config[$profile]['src_file'], array('hash' => $hash, 'ext' => 'dot'));
		$len = file_put_contents($dot_file, $dot);

		$this->templateAdd(null, 'core/graphviz_diagram', array(
				'link' => $config['renderer']['link'],
				'hash' => $hash,
				'profile' => $profile,
				'alt' => $type,
			));


		$this->out('done', $len !== FALSE);
	}

}

