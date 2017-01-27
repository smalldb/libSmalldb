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

/**
 * Interface of state machine definition reader -- converts external file to
 * %Smalldb machine definition.
 */
interface IMachineDefinitionReader
{
	/**
	 * Parse string and return fragment of state machine definition.
	 *
	 * @param $machine_type - Name of state machine (for better error messages)
	 * @param $data_string - Data to parse.
	 * @param $options - Additional options specified in master definition.
	 * @param $filename - Name of the file (or similar identifier) - only for
	 * 	debug messages.
	 * @return array - Fragment of machine definition.
	 */
	public static function loadString($machine_type, $data_string, $options = array(), $filename = null);

	/**
	 * If reader was invoked, it may need to postprocess the definition
	 * when everything is loaded (after last loadString call is completed).
	 *
	 * Postprocessing is invoked only once, even when loadString has been
	 * invoked multiple times.
	 *
	 * @param $machine_type - Name of state machine (for better error messages)
	 * @param $machine_def - Machine definition to be processed in place.
	 */
	public static function postprocessDefinition($machine_type, & $machine_def);
}

