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
	 * Return true if the file looks parsable by this reader.
	 *
	 * @param string $file_extension File extension including leading dot (e.g. ".json")
	 * @return bool
	 */
	public function isSupported(string $file_extension): bool;

	/**
	 * Parse string and return fragment of state machine definition.
	 *
	 * @param string $machine_type  Name of state machine (for better error messages)
	 * @param string $data_string  Data to parse.
	 * @param array $options  Additional options specified in master definition.
	 * @param string $filename  Name of the file (or similar identifier) - only for
	 * 	debug messages.
	 * @return array - Fragment of machine definition.
	 */
	public function loadString(string $machine_type, string $data_string, array $options = [], string $filename = null);

	/**
	 * If reader was invoked, it may need to postprocess the definition
	 * when everything is loaded (after last loadString call is completed).
	 *
	 * Postprocessing is invoked only once, even when loadString has been
	 * invoked multiple times.
	 *
	 * @param string $machine_type  Name of state machine (for better error messages)
	 * @param array $machine_def  Machine definition to be processed in place.
	 * @param array $errors  List of errors in state machine definition. Errors
	 * 	may be specified in the diagram as well.
	 *
	 * @return bool True when machine is successfully loaded, false otherwise.
	 */
	public function postprocessDefinition(string $machine_type, array & $machine_def, array & $errors);

}
