<?php
/*
 * Copyright (c) 2017, Josef Kufner  <josef@kufner.cz>
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

interface IDebugLogger
{
	function afterDebugLoggerRegistered(AbstractBackend $smalldb);
	function afterMachineCreated(AbstractBackend $smalldb, string $type, AbstractMachine $machine);
	function afterReferenceCreated(AbstractBackend $smalldb, Reference $ref, array $properties = null);
	function afterListingCreated(AbstractBackend $smalldb, IListing $listing, array $filters);
	function beforeTransition(AbstractBackend $smalldb, Reference $ref, string $old_state, string $transition_name, $args);
	function afterTransition(AbstractBackend $smalldb, Reference $ref, string $old_state, string $transition_name, string $new_state, $return_value, $returns);
}

