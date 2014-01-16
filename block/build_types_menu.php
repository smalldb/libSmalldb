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

/**
 * List all known Smalldb types of all backends in way core/out/menu understands.
 */
class B_smalldb__build_types_menu extends \Cascade\Core\Block
{
	protected $inputs = array(
		'link' => '/admin/doc/smalldb/{type}',
	);

	protected $outputs = array(
		'items' => true,
		'done' => true,
	);

	public function main()
	{
		$link = $this->in('link');

		$block_storages = $this->getCascadeController()->getBlockStorages();
		$items = array();

		foreach ($block_storages as $storage) {
			if (!($storage instanceof \Smalldb\Cascade\BlockStorage)) {
				continue;
			}

			$smalldb = $storage->getSmalldbBackend();
			$alias = $smalldb->getAlias();

			$types = $smalldb->getKnownTypes();

			foreach ($types as $type) {
				$desc = $smalldb->describeType($type);
				$items[] = array_merge($desc, array(
					'type' => $type,
					'link' => str_replace('_', '-', filename_format($link, array('type' => $type))),
				));
			}
		}
		
		$this->out('items', $items);
		$this->out('done', true);
	}
}

