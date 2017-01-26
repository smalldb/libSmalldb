<?php
/*
 * Copyright (c) 2016, Josef Kufner  <josef@kufner.cz>
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

namespace Smalldb\StateMachine;

use Smalldb\StateMachine\Utils\Utils;


/**
 * JSON reader
 */
class JsonReader implements IMachineDefinitionReader
{

	/// @copydoc IMachineDefinitionReader::loadString
	public static function loadString($data_string, $options = array(), $filename = null)
	{
		return Utils::parse_json_string($data_string, $filename);
	}


	/// @copydoc IMachineDefinitionReader::postprocessDefinition
	public static function postprocessDefinition(& $machine_def)
	{
		// Make sure the names are always set
		if (!empty($machine_def['properties'])) {
			foreach ($machine_def['properties'] as $p_name => & $property) {
				if (empty($property['name'])) {
					$property['name'] = $p_name;
				}
			}
			unset($property);
		}
		if (!empty($machine_def['actions'])) {
			foreach ($machine_def['actions'] as $a_name => & $action) {
				if (empty($action['name'])) {
					$action['name'] = $a_name;
				}
			}
			unset($action);

			// Sort actions, so they appear everywhere in defined order
			// (readers may provide them in random order)
			uasort($machine_def['actions'], function($a, $b) {
				$aw = (isset($a['weight']) ? $a['weight'] : 50);
				$bw = (isset($b['weight']) ? $b['weight'] : 50);

				if ($aw == $bw) {
					return strcoll(isset($a['label']) ? $a['label'] : $a['name'],
						isset($b['label']) ? $b['label'] : $b['name']);
				} else {
					return $aw - $bw;
				}
			});
		}
	}

}

