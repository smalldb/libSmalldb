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

class B_smalldb__describe_machine extends \Cascade\Core\Block
{
	protected $inputs = array(
		'type' => null,
	);

	protected $connections = array(
		'type' => array(),
	);	

	protected $outputs = array(
		'type' => true,
		'desc' => true,
		'done' => true,
	);

	public function main()
	{
		$type = $this->in('type');

		$block_storages = $this->getCascadeController()->getBlockStorages();

		foreach ($block_storages as $storage) {
			if (!($storage instanceof \Smalldb\Cascade\BlockStorage)) {
				continue;
			}

			$smalldb = $storage->getSmalldbBackend();
			$desc = $smalldb->describeType($type);

			if ($desc) {
				$machine = $smalldb->getMachine($type);
				$desc['primary_key'] = $machine->describeId();
				$desc['properties'] = $machine->describeAllMachineProperties();

				$this->out('type', $type);
				$this->out('desc', $desc);
				$this->out('done', true);
				return true;
			}
		}
	}

}


