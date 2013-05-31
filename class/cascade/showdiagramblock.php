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

class ShowDiagramBlock extends \Block
{

	protected $inputs = array(
		'machine_type' => array(),
		'gv_config' => array('config', 'core.graphviz'),
		'gv_profile' => 'smalldb',
		'slot' => 'default',
		'slot_weight' => 50,
	);

	protected $outputs = array(
		'done' => true,
	);

	const force_exec = true;


	/**
	 * Setup block to act as expected. Configuration is done by Smalldb 
	 * Block Storage.
	 */
	public function __construct($smalldb)
	{
		$this->smalldb = $smalldb;
	}


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

