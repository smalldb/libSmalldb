<?php declare(strict_types = 1);
/*
 * Copyright (c) 2012-2019, Josef Kufner  <josef@kufner.cz>
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

use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Provider\SmalldbProviderInterface;
use Smalldb\StateMachine\Transition\TransitionEvent;
use Smalldb\StateMachine\Utils\Hook;


/**
 * %Reference to one or more state machines. Allows you to invoke transitions in
 * the easy way by calling methods on this reference object. This is syntactic
 * sugar only, nothing really happens here.
 *
 * $id is per machine type unique identifier. It is always a single literal
 * or an array of literals for compound primary keys.
 *
 * Method call on this class invokes the transition.
 *
 * Read-only properties:
 *   - state = $machine->getState($id);
 *   - properties = $machine->getProperties($id);
 *   - ... see __get().
 *
 * Read one property (will load all of them):
 *   $ref['property']
 *
 * Flush property cache:
 *   unset($ref->properties);
 */
abstract class Reference implements ReferenceInterface, \ArrayAccess, \Iterator, \JsonSerializable
{
	/**
	 * Smalldb
	 * @var Smalldb
	 */
	protected $smalldb;

	/** @var SmalldbProviderInterface */
	private $machineProvider;

	/**
	 * Primary key (unique within $machine).
	 */
	protected $id;

	/**
	 * Cached state of the machine.
	 */
	protected $state_cache;

	/**
	 * Cached properties of the machine.
	 */
	protected $properties_cache;

	/**
	 * Cached values from views on machine properties.
	 */
	protected $view_cache;

	/**
	 * Persistent view cache, which is not flushed automaticaly after every
	 * transition.
	 */
	protected $persistent_view_cache = array();


	/************************************************************************//**
	 * @name	Hooks
	 * @{
	 *
	 * Hooks are lists of callables. Reference calls them when
	 * something interesting happens.
	 *
	 * @see Hook class
	 *
	 * @example `$ref->afterPkChanged()->addListener(function() { ... });`
	 */

	/** @var Hook|null */
	private $after_pk_changed = null;
	/** @var Hook|null */
	private $before_transition = null;
	/** @var Hook|null */
	private $after_transition = null;

	/**
	 * Hook invoked when reference primary key changes.
	 *
	 * Callback: `function($ref, $new_pk)`
	 */
	public function afterPkChanged()
	{
		return $this->after_pk_changed ?? ($this->after_pk_changed = new Hook());
	}

	/**
	 * Hook invoked before transition is invoked.
	 *
	 * Callback: `function($ref, $transition_name, $arguments)`
	 */
	public function beforeTransition()
	{
		return $this->before_transition ?? ($this->before_transition = new Hook());
	}

	/**
	 * Hook invoked after transition is invoked.
	 *
	 * Callback: `function($ref, $transition_name, $arguments, $return_value, $returns)`
	 */
	public function afterTransition()
	{
		return $this->after_transition ?? ($this->after_transition = new Hook());
	}

	/// @}


	/**
	 * Create a reference and initialize it with a given ID. To copy
	 * a reference use the clone keyword.
	 */
	public function __construct(Smalldb $smalldb, SmalldbProviderInterface $machineProvider, ...$id)
	{
		$this->invalidateCache();

		$this->smalldb = $smalldb;
		$this->machineProvider = $machineProvider;

		switch (count($id)) {
			case 0:
				throw new \InvalidArgumentException('Invalid ID - empty array makes no sense.');
			case 1:
				list($this->id) = $id;
				break;
			default:
				$this->id = $id;
				break;
		}
	}


	/**
	 * Show relevant data when using var_dump().
	 */
	public function __debugInfo() {
		return $this->properties;
	}


	/**
	 * Support for json_encode() - implementation of JsonSerializable interface
	 */
	public function jsonSerialize()
	{
		if ($this->isNullRef()) {
			return null;
		} else {
			return $this->properties;
		}
	}


	public function getMachineType(): string
	{
		return $this->machineProvider->getDefinition()->getMachineType();
	}


	public function getId()
	{
		return $this->id;
	}


	/**
	 * Get state machine definition
	 */
	public function getDefinition(): StateMachineDefinition
	{
		return $this->machineProvider->getDefinition();
	}


	/**
	 * Read state machine state
	 */
	public function getState(): string
	{
		return $this->machineProvider->getRepository()->getState($this);
	}


	/**
	 * Create pre-heated reference.
	 *
	 * @warning This may break things a lot. Be careful.
	 */
	public static function createPreheatedReference(Smalldb $smalldb, SmalldbProviderInterface $machineProvider, $properties)
	{
		$ref = new static($smalldb, $machineProvider, null);
		$ref->properties_cache = $properties;
		$ref->state_cache = $properties['state'];

		$id_properties = $machine->describeId();
		if (count($id_properties) == 1) {
			$ref->id = $properties[$id_properties[0]];
		} else {
			$id = array();
			foreach ($id_properties as $k) {
				$id[] = $properties[$k];
			}
			$ref->id = $id;
		}

		return $ref;
	}


	/**
	 * Returns true if reference points only to machine type. Such 
	 * reference may not be used to modify any machine, however, it can be 
	 * used to invoke 'create'-like transitions.
	 */
	public function isNullRef()
	{
		return $this->id === null || $this->id === array();
	}


	/**
	 * Drop all cached data.
	 */
	public function invalidateCache(): void
	{
		$this->state_cache = null;
		$this->properties_cache = null;
		$this->view_cache = array();
	}


	/**
	 * Function call is transition invocation. Just forward it to backend.
	 *
	 * When transition returns new ID, the reference is updated to keep 
	 * it pointing to the same state machine.
	 */
	public function __call($name, $arguments)
	{
		$transitionEvent = $this->invokeTransition($name, $arguments);
		return $transitionEvent->getReturnValue();
	}


	/**
	 * Invoke transition of the state machine.
	 */
	public function invokeTransition(string $transitionName, ...$args)
	{
		if ($this->before_transition) {
			$this->before_transition->emit($this, $transitionName, $args);
		}
		$oldId = $this->id;

		$this->invalidateCache();

		$transitionEvent = new TransitionEvent($this, $transitionName, $args);
		$transitionEvent->onNewId(function($newId) use ($oldId) {
			$this->id = $newId;

			if ($this->after_pk_changed) {
				$this->after_pk_changed->emit($this, $oldId, $this->id);
			}
		});
		$this->machineProvider->getTransitionsDecorator()->invokeTransition($transitionEvent);

		if ($this->after_transition) {
			$this->after_transition->emit($this, $transitionName, $args, $transitionEvent);
		}

		return $transitionEvent;
	}


	/**
	 * Get data from machine
	 *
	 * If you want to retrieve both state and properties, ask for 
	 * properties first. The state may get pre-cached.
	 */
	public function __get($key)
	{
		switch ($key) {
			case 'id':
				return $this->id;
			case 'machine':
				return $this->machine;
			case 'machineType':
			case 'machine_type':
				return $this->machine->getMachineType();
			case 'state':
				if ($this->state_cache === null) {
					$this->state_cache = $this->machine->getState($this->id);
				}
				return $this->state_cache;
			case 'properties':
				if ($this->properties_cache === null) {
					$this->properties_cache = $this->machine->getProperties($this->id, $this->state_cache);
					$this->properties_cache['state'] = $this->state_cache;
				}
				return $this->properties_cache;
			case 'actions':
				return $this->machine->getAvailableTransitions($this);
			default:
				return $this->machine->getView($this->id, $key, $this->properties_cache, $this->view_cache, $this->persistent_view_cache);
		}
	}


	/**
	 * Flush cached data.
	 */
	public function __unset($key)
	{
		switch ($key) {
			case 'id':
			case 'machine':
			case 'machineType':
			case 'actions':
				throw new InvalidArgumentException('Property is not cached: '.$key);
			case 'state':
			case 'properties':
				$this->invalidateCache();
				break;
			default:
				throw new InvalidArgumentException('Unknown property: '.$key);
		}
	}


	/******************************************************************//**
	 * @}
	 * @name 	Array access for properties
	 * @{
	 */

	public function offsetExists($offset)
	{
		if ($this->properties_cache === null) {
			$this->properties_cache = $this->machine->getProperties($this->id);
		}
		return array_key_exists($offset, $this->properties_cache);
	}

	public function offsetGet($offset)
	{
		if ($this->properties_cache === null) {
			$this->properties_cache = $this->machine->getProperties($this->id);
		}
		return $this->properties_cache[$offset];
	}

	public function offsetSet($offset, $value)
	{
		throw new InvalidArgumentException('Cannot set property: Property cache is read only.');
	}

	public function offsetUnset($offset)
	{
		throw new InvalidArgumentException('Cannot unset property: Property cache is read only.');
	}


	/******************************************************************//**
	 * @}
	 * @name	Iterator interface to iterate over properties
	 * @{
	 */

	function rewind() {
		if ($this->properties_cache === null) {
			$this->properties_cache = $this->machine->getProperties($this->id);
		}
		return reset($this->properties_cache);
	}

	function current() {
		if ($this->properties_cache === null) {
			$this->properties_cache = $this->machine->getProperties($this->id);
		}
		return current($this->properties_cache);
	}

	function key() {
		if ($this->properties_cache === null) {
			$this->properties_cache = $this->machine->getProperties($this->id);
		}
		return key($this->properties_cache);
	}

	function next() {
		if ($this->properties_cache === null) {
			$this->properties_cache = $this->machine->getProperties($this->id);
		}
		return next($this->properties_cache);
	}

	function valid() {
		if ($this->properties_cache === null) {
			$this->properties_cache = $this->machine->getProperties($this->id);
		}
		return key($this->properties_cache) !== null;
	}

	/** @} */
}

