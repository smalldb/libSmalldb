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

class B_smalldb__show_properties extends Block
{

	protected $inputs = array(
		'desc' => array(),
		'slot' => 'default',
		'slot_weight' => 50,
	);

	protected $outputs = array(
		'done' => true,
	);

	const force_exec = true;


	public function main()
	{
		$desc = $this->in('desc');
		if (!$desc) {
			return;
		}

		$table = new TableView();

		$table->add_column('text', array(
				'title' => _('PK'),
				'title_tooltip' => _('Primary key'),
				'value' => function($row) use ($desc) { return in_array($row['name'], $desc['primary_key']) ? _("\xE2\x97\x8F") : ''; },
				'width' => '1%',
			));
		$table->add_column('text', array(
				'title' => _('Property'),
				'key' => 'name',
			));
		$table->add_column('text', array(
				'title' => _('Type'),
				'key' => 'type',
			));
		$table->add_column('number', array(
				'title' => _('Size'),
				'value' => function($row) { return $row['size'] > 0 ? $row['size'] : null; },
				'width' => '1%',
			));
		$table->add_column('text', array(
				'title' => _('Default value'),
				'key' => 'default',
			));
		$table->add_column('text', array(
				'title' => _('Optional'),
				'value' => function($row) { return $row['optional'] ? _('Yes') : _('No'); },
			));

		$table->set_data($desc['properties']);
                $this->template_add(null, 'core/table', $table);
                $this->out('done', true);		
	}

}


