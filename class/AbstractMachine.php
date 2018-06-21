<?php
/*
 * Copyright (c) 2012-2017, Josef Kufner  <josef@kufner.cz>
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

use Smalldb\StateMachine\Graph\Graph;
use Smalldb\StateMachine\Graph\Node;
use Smalldb\StateMachine\Graph\GraphSearch;
use Smalldb\StateMachine\Utils\Utils;


/**
 * Implementation of the state machine transitions.
 *
 * One instance of this class implements state machine transitions of all
 * machines of this type. When a transition is invoked a Reference object is
 * passed as the first parameter of the transition method (a protected method
 * of the same name as transition). The Reference provides ID of the state
 * machine instance and may contain cached state data.
 *
 * ### Configuration Schema
 *
 * The state machine is configured using JSON object passed to the constructor
 * (the `$config` parameter). The object must match the following JSON schema
 * ([JSON format](AbstractMachine.schema.json)):
 *
 * @htmlinclude doxygen/html/AbstractMachine.schema.html
 */
abstract class AbstractMachine
{
	/**
	 * Return value of invoked transition is just some value.
	 */
	const RETURNS_VALUE = null;

	/**
	 * Return value of invoked transition is new ID of the state machine.
	 */
	const RETURNS_NEW_ID = 'new_id';

	/**
	 * Smalldb entry point
	 *
	 * @var Smalldb
	 */
	protected $smalldb = null;

	/**
	 * Identification of the machine.
	 */
	protected $machine_type;

	/**
	 * Class name of the Reference class
	 */
	protected $reference_class = null;

	/**
	 * Debugger
	 * @var IDebugLogger
	 */
	private $debug_logger = null;

	/**
	 * List of additional diagram parts in Dot language provided by backend (and its readers).
	 *
	 * @see BpmnReader, GraphMLReader
	 */
	protected $state_diagram_extras = [];
	protected $state_diagram_extras_json = [];

	/**
	 * List of errors in state machine definition.
	 *
	 * Populated by IMachineDefinitionReader when loading the state mahcine.
	 */
	protected $errors = [];

	/**
	 * URL format string where machine is located, usualy only the path
	 * part, e.g. "/machine-type/{id}".
	 *
	 * To make reverse routes work, only entire path fragment can be
	 * replaced by symbol:
	 *
	 *   - Good: /machine-type/foo/{id}/bar
	 *   - Bad:  /machine-type/foo-{id}/bar
	 *
	 * This is limitation of default router, not this class.
	 *
	 * If URL does not start with slash, router will ignore it.
	 */
	protected $url_fmt;


	/**
	 * URL format string where parent of this machine is located, usualy only the path
	 * part, e.g. "/parent-type/{id}/machine-type".
	 *
	 * @see $url_fmt
	 */
	protected $parent_url_fmt;

	/**
	 * URL format string for redirect-after-post. When machine is part of
	 * another entity, it may be reasonable to redirect to such entity
	 * instead of showing this machine alone. If not set, url_fmt is used.
	 *
	 * @see $url_fmt
	 */
	protected $post_action_url_fmt;

	/**
	 * Descriptions of all known states -- key is state id, value is * description
	 *
	 * Fill these data in self::initializeMachine().
	 */
	protected $states; /* = array(
		'state_name' => array(
			'label' => _('Human readable name (short)'),
			'description' => _('Human readable description (sentence or two).'),
			'group' => 'state_group_name',	// See $state_groups.
			'color' => '#eeeeee',		// 6 digit hex code (Graphviz and CSS compatible) [optional]
		),
		...
	); */

	/**
	 * State groups. This state machine is flat -- no sub-states. To make
	 * diagrams easier to read, this allows to group relevant states
	 * together. This has no influence on the behaviour.
	 *
	 * Fill these data in self::initializeMachine().
	 */
	protected $state_groups; /* = array(
		'group_name' => array(
			'label' => _('Human readable name (short)'),
			'color' => '#eeeeee',		// 6 digit hex code (Graphviz and CSS compatible) [optional]
			'groups' => array( ... nested state groups ... ),
		),
	); */

	/**
	 * Description of all known actions -- key is action name.
	 *
	 * Each action has transitions (transition function) and each
	 * transition can end in various different states (assertion function).
	 *
	 * Fill these data in self::initializeMachine().
	 */
	protected $actions; /* = array(
		'action_name' => array(
			'label' => _('Human readable name (short)'),
			'description' => _('Human readable description (sentence or two).'),
			'returns' => 'new_id',	// Use this if machine ID is changed after transition. If null, value is returned as is.
			'transitions' => array(
				'source_state' => array(
					'targets' => array('target_state', ... ),
					'method' => 'method_name', // same as action_name if missing
				),
			),
		)
	); */

	/**
	 * Default access policy
	 *
	 * When transition nor action has policy specified, this one is used.
	 *
	 * @note This does not affect read_access_policy.
	 */
	protected $default_access_policy = null;

	/**
	 * Read access policy
	 *
	 * Policy applied when reading machine state.
	 *
	 * @note Not affected by default_access_policy.
	 */
	protected $read_access_policy = null;

	/**
	 * Listing access policy
	 *
	 * Policy applied when creating machine listing (read_access_policy is also applied).
	 *
	 * @note Not affected by default_access_policy.
	 */
	protected $listing_access_policy = null;

	/**
	 * Access policies
	 *
	 * Description of all access policies available to this state machine
	 * type.
	 */
	protected $access_policies; /* = array(
		'policy_name' => array(
			... policy specific options.
		)
	); */


	/**
	 * Description of machine properties -- key is property name.
	 *
	 * Each property has some metadata available, so it is possible to
	 * generate simple forms or present data to user without writing
	 * per-machine specific templates. These metadata should be
	 * as little implementation specific as possible.
	 */
	protected $properties; /* = array(
		'property_name' => array(
			'label' => _('Human readable name (short)'),
			'description' => _('Human readable description (sentence or two).'),
			'type' => 'type_identifier', // Logical type -- eg. 'price', not 'int'.
			'enum' => array('key' => _('Label'), ...), // Available values for enum types
			'note' => _('Some additional note displayed in forms under the field.'),
		),
	); */


	/**
	 * Description of machine views -- key is view name.
	 *
	 * Some properties may require heavy computations and may not be used 
	 * as often as other properties, so it is reasonable to put them into 
	 * separate view instead.
	 *
	 * These views are just like SQL views. They are somehow transformed 
	 * view on the machine and its properties. There is no explicit 
	 * definition of structure of the view -- it can be single value, 
	 * subset of properties, or something completely different.
	 *
	 * View names must not collide with reference names, since references
	 * are special cases of views.
	 */
	protected $views; /* = array(
		'view_name' => array(
			'label' => _('Human readable name (short)'),
			'description' => _('Human readable description (sentence or two).'),
			'properties' => array('property_name', ...), // Names of included properties, if the view is subset of properties.
		),
	); */


	/**
	 * Description of machine references.
	 *
	 * Often it is useful to reference one state machine from another, just
	 * like foreign keys in SQL.
	 *
	 * Smalldb is limited to reference using primary keys only.
	 *
	 * References are added to views.
	 */
	protected $references; /* = array(
		'reference_name' => array(
			'machine_type' => 'Type of referenced machine',
			'machine_id' => [ 'referring_properties', ... ],
			'properties' => array('fake_property_name' => 'referred_property', ...), // Names of imported properties in listings.
		),
	); */


	/**
	 * Constructor is left empty so the machine implementation can use it to
	 * obtain resources automatically via dependency injection.
	 */
	public function __construct()
	{
		// Nop.
	}


	/**
	 * Configure machine. Machine gets reference to Smalldb entry point,
	 * name of its type (under which is this machine registered) and
	 * optional array of additional configuration (passed directly to
	 * initializeMachine method).
	 *
	 * This method is called only once, right after backend creates/gets
	 * instance of this class.
	 */
	public final function initializeMachine(Smalldb $smalldb, string $type, array $config)
	{
		if ($this->smalldb !== null) {
			throw new RuntimeException('Machine is already configured.');
		}
		$this->smalldb = $smalldb;
		$this->machine_type = $type;
		$this->configureMachine($config);

		// Create default machine (see FlupdoCrudMachine)?
		if (empty($config['no_default_machine'])) {
			$this->setupDefaultMachine($config);
		}
	}


	/**
	 * @return string|null
	 */
	public function getReferenceClassName()
	{
		return $this->reference_class;
	}


	/**
	 * Set debug logger
	 */
	public function setDebugLogger(IDebugLogger $debug_logger)
	{
		$this->debug_logger = $debug_logger;
	}


	/**
	 * Get debug logger
	 */
	public function getDebugLogger()
	{
		return $this->debug_logger;
	}


	/**
	 * Define state machine used by all instances of this type.
	 *
	 * @warning Derived classes always must call parent's implementation.
	 */
	protected function configureMachine(array $config)
	{
		// Load configuration
		$this->loadMachineConfig($config, [
			'reference_class',
			'states', 'state_groups', 'actions', 'errors',
			'access_policies', 'default_access_policy', 'read_access_policy', 'listing_access_policy',
			'properties', 'views', 'references',
			'url_fmt', 'parent_url_fmt', 'post_action_url_fmt',
			'state_diagram_extras', 'state_diagram_extras_json'
		]);
	}


	/**
	 * Setup default machine when initializeMachine is finished.
	 *
	 * This method should check what is already defined to not overwrite
	 * provided definitions.
	 *
	 * @note This method is not called when $config['no_default_machine'] is set.
	 *
	 * @warning Derived classes may not call parent's implementation.
	 */
	protected function setupDefaultMachine(array $config)
	{
		// nop
	}


	/**
	 * Merge $config into state machine member variables (helper method).
	 *
	 * Already set member variables are not overwriten. If the member
	 * variable is an array, the $config array is merged with the config,
	 * overwriting only matching keys (using `array_replace_recursive`).
	 *
	 * @param $config Configuration passed to state machine
	 * @param $keys List of feys from $config to load into member variables
	 * 	of the same name.
	 */
	protected function loadMachineConfig(array $config, array $keys)
	{
		foreach ($keys as $k) {
			if (isset($config[$k])) {
				if ($this->$k === null) {
					$this->$k = $config[$k];
				} else if (is_array($this->$k) && is_array($config[$k])) {
					$this->$k = array_replace_recursive($this->$k, $config[$k]);
				}
			}
		}
	}


	/**
	 * Get errors found while loading the machine definition.
	 *
	 * @return List of errors.
	 */
	public function getErrors()
	{
		return $this->errors;
	}


	/**
	 * Returns true if user has required access_policy to invoke a 
	 * transition, which requires given access_policy.
	 */
	abstract protected function checkAccessPolicy($access_policy, Reference $ref);


	/**
	 * Get current state of state machine.
	 *
	 * @note getState() does not check read access policy.
	 */
	abstract public function getState($id);


	/**
	 * Get properties of state machine, including it's state.
	 *
	 * If state machine uses property views, not all properties may be 
	 * returned by this method. Some of them may be computed or too big.
	 *
	 * Some implementations may store current state to $state_cache, so it 
	 * does not have to be retireved in the second query.
	 *
	 * @note getProperties() does not check read access policy.
	 */
	abstract public function getProperties($id, & $state_cache = null);


	/**
	 * Get properties in given view.
	 *
	 * Just like SQL view, the property view is transformed set of 
	 * properties. These views are useful when some properties require 
	 * heavy calculations.
	 *
	 * Keep in mind, that view name must not interfere with Reference 
	 * properties, otherwise the view is inaccessible directly.
	 *
	 * Array $properties_cache is supplied by Reference class to make some 
	 * calculations faster and without duplicate database queries.
	 * Make sure these cached properties are up to date or null.
	 *
	 * Array $view_cache is writable cache inside the Reference class, 
	 * flushed together with $properties_cache. If cache is empty, but 
	 * available, an empty array is supplied.
	 *
	 * Array $persistent_view_cache is kept untouched for entire Reference 
	 * lifetime. If cache is empty, but available, an empty array is 
	 * supplied.
	 *
	 * @see AbstractMachine::calculateViewValue()
	 */
	public final function getView($id, $view, & $properties_cache = null, & $view_cache = null, & $persistent_view_cache = null)
	{
		// Check cache
		if (isset($view_cache[$view])) {
			return $view_cache[$view];
		}

		switch ($view) {
			case 'url':
				// Get URL of this state machine
				return $this->urlFormat($id, $this->url_fmt, $properties_cache);
			case 'parent_url':
				// Get URL of parent state machine or collection or whatever it is
				if ($this->parent_url_fmt !== null) {
					return $this->urlFormat($id, $this->parent_url_fmt, $properties_cache);
				} else {
					return null;
				}
			case 'post_action_url':
				// Get URL of redirect-after-post
				return $this->urlFormat($id, $this->post_action_url_fmt !== null ? $this->post_action_url_fmt : $this->url_fmt, $properties_cache);
			default:
				// Check references
				if (isset($this->references[$view])) {
					// Populate properties cache if empty
					if ($properties_cache === null) {
						$properties_cache = $this->getProperties($id);
					}

					// Create reference & cache it
					return ($view_cache[$view] = $this->resolveMachineReference($view, $properties_cache));
				} else {
					return ($view_cache[$view] = $this->calculateViewValue($id, $view, $properties_cache, $view_cache, $persistent_view_cache));
				}
		}
	}


	/**
	 * Calculate value of a view.
	 *
	 * Returned value is cached in $view_cache until a transition occurs or
	 * the cache is invalidated.
	 */
	protected function calculateViewValue($id, $view, & $properties_cache = null, & $view_cache = null, & $persistent_view_cache = null)
	{
		throw new InvalidArgumentException('Unknown view "'.$view.'" requested on machine "'.$this->machine_type.'".');
	}


	/**
	 * Create URL using properties and given format.
	 */
	protected function urlFormat($id, $url_fmt, $properties_cache)
	{
		if (isset($url_fmt)) {
			if ($properties_cache === null) {
				// URL contains ID only, so there is no need to load properties.
				return Utils::filename_format($url_fmt, array_combine($this->describeId(), (array) $id));
			} else {
				// However, if properties are in cache, it is better to use them.
				return Utils::filename_format($url_fmt, $properties_cache);
			}
		} else {
			// Default fallback to something reasonable. It might not work, but meh.
			return '/'.$this->machine_type.'/'.(is_array($id) ? join('/', $id) : $id);
		}
	}


	/**
	 * Helper function to resolve reference to another machine.
	 *
	 * @param string $reference_name    Name of the reference in AbstractMachine::references.
	 * @param array  $properties_cache  Properties of referencing machine.
	 * @return Reference to referred machine.
	 */
	protected function resolveMachineReference(string $reference_name, array $properties_cache): Reference
	{
		if (!isset($this->references[$reference_name])) {
			throw new \InvalidArgumentException('Unknown reference: '.$reference_name);
		}

		// Get reference configuration
		$r = $this->references[$reference_name];

		// Get referenced machine id
		$ref_machine_id = array();
		foreach ($r['machine_id'] as $mid_prop) {
			$ref_machine_id[] = $properties_cache[$mid_prop];
		}

		// Get referenced machine type
		$ref_machine_type = $r['machine_type'];

		return $this->smalldb->getMachine($ref_machine_type)->ref($ref_machine_id);
	}


	/**
	 * Returns true if transition can be invoked right now.
	 *
	 * TODO: Transition should not have full definition of the policy, only
	 * 	its name. Definitions should be in common place.
	 */
	public function isTransitionAllowed(Reference $ref, $transition_name, $state = null, & $access_policy = null)
	{
		if ($state === null) {
			$state = $ref->state;
		}

		if ($transition_name == '') {
			// Read access
			$access_policy = $this->read_access_policy;
			return $this->checkAccessPolicy($this->read_access_policy, $ref);
		} else if (isset($this->actions[$transition_name]['transitions'][$state])) {
			$tr = $this->actions[$transition_name]['transitions'][$state];
			if (isset($tr['access_policy'])) {
				// Transition-specific policy
				$access_policy = $tr['access_policy'];
			} else if (isset($this->actions[$transition_name]['access_policy'])) {
				// Action-specific policy (for all transitions of this name)
				$access_policy = $this->actions[$transition_name]['access_policy'];
			} else {
				// No policy, use default
				$access_policy = $this->default_access_policy;
			}
			return $this->checkAccessPolicy($access_policy, $ref);
		} else {
			// Not a valid transition
			return false;
		}
	}


	/**
	 * Get list of all available actions for state machine instance identified by $id.
	 */
	public function getAvailableTransitions(Reference $ref, $state = null)
	{
		if ($state === null) {
			$state = $ref->state;
		}

		$available_transitions = array();

		foreach ($this->actions as $a => $action) {
			if (!empty($action['transitions'][$state]) && $this->isTransitionAllowed($ref, $a, $state)) {
				$available_transitions[] = $a;
			}
		}

		return $available_transitions;
	}


	/**
	 * Invoke state machine transition. State machine is not instance of
	 * this class, but it is represented by record in database.
	 */
	public function invokeTransition(Reference $ref, $transition_name, $args, & $returns, callable $new_id_callback = null)
	{
		if (!empty($this->errors)) {
			throw new StateMachineHasErrorsException('Cannot use state machine with errors in definition: "'.$this->machine_type.'".');
		}

		$state = $ref->state;

		if ($this->debug_logger) {
			$this->debug_logger->beforeTransition($this, $ref, $state, $transition_name, $args);
		}

		// get action
		if (isset($this->actions[$transition_name])) {
			$action = $this->actions[$transition_name];
		} else {
			throw new TransitionException('Unknown transition "'.$transition_name.'" requested on machine "'.$this->machine_type.'".');
		}

		// get transition (instance of action)
		if (isset($action['transitions'][$state])) {
			$transition = $action['transitions'][$state];
		} else {
			throw new TransitionException('Transition "'.$transition_name.'" not found in state "'.$state.'" of machine "'.$this->machine_type.'".');
		}
		$transition = array_merge($action, $transition);

		// check access_policy
		if (!$this->isTransitionAllowed($ref, $transition_name, $state, $access_policy)) {
			throw new TransitionAccessException('Access denied to transition "'.$transition_name.'" '
				.'of machine "'.$this->machine_type.'" (used access policy: "'.$access_policy.'").');
		}

		// invoke pre-transition checks
		if (isset($transition['pre_check_methods'])) {
			foreach ($transition['pre_check_methods'] as $m) {
				if (call_user_func(array($this, $m), $ref, $transition_name, $args) === false) {
					throw new TransitionPreCheckException('Transition "'.$transition_name.'" aborted by method "'.$m.'" of machine "'.$this->machine_type.'".');
				}
			}
		}

		// get method
		$method = isset($transition['method']) ? $transition['method'] : $transition_name;
		$prefix_args = isset($transition['args']) ? $transition['args'] : array();

		// prepare arguments -- the first argument is $ref, rest are $args as passed to $ref->action($args...).
		if (!empty($prefix_args)) {
			array_splice($args, 0, 0, $prefix_args);
		}
		array_unshift($args, $ref);

		// invoke method
		$ret = call_user_func_array(array($this, $method), $args);

		// interpret return value
		$returns = @ $action['returns'];
		switch ($returns) {
			case self::RETURNS_VALUE:
				// nop, just pass it back
				break;
			case self::RETURNS_NEW_ID:
				if ($new_id_callback !== null) {
					$new_id_callback($ret);
				}
				break;
			default:
				throw new RuntimeException('Unknown semantics of the return value: '.$returns);
		}

		// invalidate cached state and properties data in $ref
		unset($ref->properties);

		// check result using assertion function
		$new_state = $ref->state;
		$target_states = $transition['targets'];
		if (!is_array($target_states)) {
			throw new TransitionException('Target state is not defined for transition "'.$transition_name.'" from state "'.$state.'" of machine "'.$this->machine_type.'".');
		}
		if (!in_array($new_state, $target_states)) {
			throw new RuntimeException('State machine ended in unexpected state "'.$new_state
				.'" after transition "'.$transition_name.'" from state "'.$state.'" of machine "'.$this->machine_type.'". '
				.'Expected states: '.join(', ', $target_states).'.');
		}

		// invoke post-transition callbacks
		if (isset($transition['post_transition_methods'])) {
			foreach ($transition['post_transition_methods'] as $m) {
				call_user_func(array($this, $m), $ref, $transition_name, $args, $ret);
			}
		}

		// state changed notification
		if ($state != $new_state) {
			$this->onStateChanged($ref, $state, $transition_name, $new_state);
		}

		if ($this->debug_logger) {
			$this->debug_logger->afterTransition($this, $ref, $state, $transition_name, $new_state, $ret, $returns);
		}

		return $ret;
	}


	/**
	 * Called when state is changed, when transition invocation is completed.
	 */
	protected function onStateChanged(Reference $ref, $old_state, $transition_name, $new_state)
	{
		// TODO: Use Hook
	}


	/**
	 * Get type of this machine.
	 */
	public function getMachineType()
	{
		return $this->machine_type;
	}


	/**
	 * Helper to create Reference to this machine.
	 *
	 * @see AbstractBackend::ref
	 */
	public function ref($id): Reference
	{
		$ref = new $this->reference_class($this->smalldb, $this, $id);
		if ($this->debug_logger) {
			$this->debug_logger->afterReferenceCreated(null, $ref);
		}
		return $ref;
	}


	/**
	 * Helper to create null Reference to this machine.
	 *
	 * @see AbstractBackend::nullRef
	 */
	public function nullRef(): Reference
	{
		$ref = new $this->reference_class($this->smalldb, $this, null);
		if ($this->debug_logger) {
			$this->debug_logger->afterReferenceCreated(null, $ref);
		}
		return $ref;
	}


	/**
	 * Create pre-heated reference using properties loaded from elsewhere.
	 *
	 * @warning This may break things a lot. Be careful.
	 */
	public function hotRef($properties): Reference
	{
		$ref = $this->reference_class::createPreheatedReference($this->smalldb, $this, $properties);
		if ($this->debug_logger) {
			$this->debug_logger->afterReferenceCreated(null, $ref, $properties);
		}
		return $ref;
	}


	/**
	 * Perform self-check.
	 *
	 * TODO: Extend this method with more checks.
	 *
	 * @see AbstractBackend::performSelfCheck()
	 */
	public function performSelfCheck()
	{
		$results = [];

		$results['id'] = $this->describeId();
		$results['class'] = get_class($this);
		$results['missing_methods'] = [];
		$results['errors'] = $this->errors;

		foreach ($this->describeAllMachineActions() as $a => $action) {
			foreach ($action['transitions'] as $t => $transition) {
				$transition = array_merge($action, $transition);
				$method = isset($transition['method']) ? $transition['method'] : $a;
				if (!is_callable([$this, $method])) {
					$results['missing_methods'][] = $method;
				}
			}
			sort($results['missing_methods']);
			$results['missing_methods'] = array_unique($results['missing_methods']);
		}

		$results['unreachable_states'] = $this->findUnreachableStates();

		return $results;
	}


	/**
	 * Run DFS from not-exists state and return list of unreachable states.
	 */
	public function findUnreachableStates(): array
	{
		$g = new Graph();
		$g->indexNodeAttr('unreachable');

		$g->createNode('', ['unreachable' => false]);
		foreach ($this->states as $s => $state) {
			if ($s !== '') {
				$g->createNode($s, ['unreachable' => true]);
			}
		}

		foreach ($this->actions as $a => $action) {
			foreach ($action['transitions'] ?? [] as $source => $transition) {
				foreach ($transition['targets'] ?? [] as $target) {
					$g->createEdge(null, $g->getNode($source), $g->getNode($target), []);
				}
			}
		}

		GraphSearch::DFS($g)
			->onNode(function (Node $node) use ($g) {
				$node->setAttr('unreachable', false);
				return true;
			})
			->start([$g->getNode('')]);

		return array_map(function(Node $n) { return $n->getId(); }, $g->getNodesByAttr('unreachable', true));
	}


	/******************************************************************//**
	 *
	 * \name	Reflection API
	 *
	 * @{
	 */


	/**
	 * Reflection: Describe ID (primary key).
	 *
	 * Returns array of all parts of the primary key and its
	 * types (as strings). If primary key is not compound, something
	 * like array('id' => 'string') is returned.
	 *
	 * Order of the parts may be mandatory.
	 */
	abstract public function describeId();


	/**
	 * Get URL format.
	 *
	 * Format string for Utils::filename_format().
	 */
	public function getUrlFormat()
	{
		return $this->url_fmt;
	}


	/**
	 * Get prent URL format. Parent URL is URL of collection or something
	 * of which is this machine part of.
	 *
	 * Format string for Utils::filename_format().
	 */
	public function getParentUrlFormat()
	{
		return $this->parent_url_fmt;
	}


	/**
	 * Get URL for redirect-after-post.
	 *
	 * Format string for Utils::filename_format().
	 */
	public function getPostActionUrlFormat()
	{
		return $this->post_action_url_fmt;
	}


	/**
	 * Get mtime of machine implementation.
	 *
	 * Useful to detect outdated cache entry in generated documentation.
	 *
	 * No need to override this method, it handles inherited classes 
	 * correctly. However if machine is loaded from database, a new 
	 * implementation is needed.
	 *
	 * This does not include filemtime(__FILE__) intentionaly.
	 */
	public function getMachineImplementationMTime()
	{
		$reflector = new \ReflectionObject($this);
		return filemtime($reflector->getFilename());
	}


	/**
	 * Reflection: Get all states
	 *
	 * List of can be filtered by section, just like getAllMachineActions 
	 * method does.
	 */
	public function getAllMachineStates($having_section = null)
	{
		if ($having_section === null) {
			return array_keys($this->states);
		} else {
			return array_keys(array_filter($this->states,
				function($a) use ($having_section) { return !empty($a[$having_section]); }));
		}
	}


	/**
	 * Reflection: Describe given machine state
	 *
	 * Returns state description in array or null. If field is specified, 
	 * only given field is returned.
	 */
	public function describeMachineState($state, $field = null)
	{
		if ($field === null) {
			return @ $this->states[$state];
		} else {
			return @ $this->states[$state][$field];
		}
	}


	/**
	 * Reflection: Describe all states
	 */
	public function describeAllMachineStates($having_section = null)
	{
		if ($having_section === null) {
			return $this->states;
		} else {
			return array_filter($this->states,
				function($a) use ($having_section) { return !empty($a[$having_section]); });
		}
	}


	/**
	 * Reflection: Get all actions (transitions)
	 *
	 * List of actions can be filtered by section defined in action
	 * configuration. For example $this->getAllMachineStates('block') will
	 * return only actions which have 'block' configuration defined.
	 * Requested section must contain non-empty() value.
	 */
	public function getAllMachineActions($having_section = null)
	{
		if ($having_section === null) {
			return array_keys($this->actions);
		} else {
			return array_keys(array_filter($this->actions,
				function($a) use ($having_section) { return !empty($a[$having_section]); }));
		}
	}


	/**
	 * Reflection: Describe given machine action (transition)
	 *
	 * Returns action description in array or null. If field is specified, 
	 * only given field is returned.
	 */
	public function describeMachineAction($action, $field = null)
	{
		if ($field === null) {
			return @ $this->actions[$action];
		} else {
			return @ $this->actions[$action][$field];
		}
	}


	/**
	 * Reflection: Describe all actions (transitions)
	 */
	public function describeAllMachineActions($having_section = null)
	{
		if ($having_section === null) {
			return $this->actions;
		} else {
			return array_filter($this->actions,
				function($a) use ($having_section) { return !empty($a[$having_section]); });
		}
	}


	/**
	 * Reflection: Get all properties
	 *
	 * List of can be filtered by section, just like getAllMachineActions 
	 * method does.
	 */
	public function getAllMachineProperties($having_section = null)
	{
		if ($having_section === null) {
			return array_keys($this->properties);
		} else {
			return array_keys(array_filter($this->properties,
				function($a) use ($having_section) { return !empty($a[$having_section]); }));
		}
	}


	/**
	 * Reflection: Describe given property
	 *
	 * Returns property description in array or null. If field is 
	 * specified, only given field is returned.
	 */
	public function describeMachineProperty($property, $field = null)
	{
		if ($field === null) {
			return @ $this->properties[$property];
		} else {
			return @ $this->properties[$property][$field];
		}
	}


	/**
	 * Reflection: Describe all properties
	 *
	 * Returns array of all properties and their descriptions.
	 * See describeMachineProperty and getAllMachineProperties.
	 */
	public function describeAllMachineProperties($having_section = null)
	{
		if ($having_section === null) {
			return $this->properties;
		} else {
			return array_filter($this->properties,
				function($a) use ($having_section) { return !empty($a[$having_section]); });
		}
	}


	/**
	 * Reflection: Get all views
	 *
	 * List of can be filtered by section, just like getAllMachineActions 
	 * method does.
	 */
	public function getAllMachineViews($having_section = null)
	{
		if ($having_section === null) {
			return array_keys((array) @ $this->views);
		} else {
			return array_keys(array_filter((array) @ $this->views,
				function($a) use ($having_section) { return !empty($a[$having_section]); }));
		}
	}


	/**
	 * Reflection: Describe given view
	 *
	 * Returns view description in array or null. If field is 
	 * specified, only given field is returned.
	 */
	public function describeMachineView($view, $field = null)
	{
		if ($field === null) {
			return @ $this->views[$view];
		} else {
			return @ $this->views[$view][$field];
		}
	}


	/**
	 * Reflection: Describe all views
	 */
	public function describeAllMachineViews($having_section = null)
	{
		if ($having_section === null) {
			return (array) @ $this->views;
		} else {
			return array_filter((array) @ $this->views,
				function($a) use ($having_section) { return !empty($a[$having_section]); });
		}
	}


	/**
	 * Reflection: Get all references
	 *
	 * List of can be filtered by section, just like getAllMachineActions 
	 * method does.
	 */
	public function getAllMachineReferences($having_section = null)
	{
		if ($having_section === null) {
			return array_keys((array) @ $this->references);
		} else {
			return array_keys(array_filter((array) @ $this->references,
				function($a) use ($having_section) { return !empty($a[$having_section]); }));
		}
	}


	/**
	 * Reflection: Describe given reference
	 *
	 * Returns reference description in array or null. If field is 
	 * specified, only given field is returned.
	 */
	public function describeMachineReference($reference, $field = null)
	{
		if ($field === null) {
			return @ $this->references[$reference];
		} else {
			return @ $this->references[$reference][$field];
		}
	}


	/**
	 * Reflection: Describe all references
	 */
	public function describeAllMachineReferences($having_section = null)
	{
		if ($having_section === null) {
			return (array) @ $this->references;
		} else {
			return array_filter((array) @ $this->references,
				function($a) use ($having_section) { return !empty($a[$having_section]); });
		}
	}


	/**
	 * Export state machine as JSON siutable for Grafovatko.
	 *
	 * Usage: json_encode($machine->exportJson());
	 *
	 * @see https://grafovatko.smalldb.org/
	 *
	 * @param $debug_opts Machine-specific debugging options - passed to
	 * 	exportDotRenderDebugData(). If empty/false, no debug data are
	 * 	added to the diagram.
	 * @return array  Array suitable for json_encode().
	 */
	public function exportJson($debug_opts = false): array
	{
		$nodes = [
			[
				"id" => "BEGIN",
				"shape" => "uml.initial_state",
			]
		];
		$edges = [];

		// States
		if (!empty($this->states)) {
			$is_unreachable_state = array_fill_keys($this->findUnreachableStates(), true);

			foreach ($this->states as $s => $state) {
				if ($s != '') {
					$n = [
						"id" => 's_'.$s,
						"label" => $s,
						"fill" => $state['color'] ?? "#eee",
						"shape" => "uml.state",
					];

					// Highlight state if it is unreachable
					if (!empty($is_unreachable_state[$s])) {
						$n['label'] .= "\n(unreachable)";
						$n['color'] = "#ff0000";
						$n['fill'] = "#ffeedd";
					}

					$nodes[] = $n;
				}
			}
		}

		$have_final_state = false;
		$missing_states = array();

		// Transitions
		$used_actions = array();
		if (!empty($this->actions)) {
			foreach ($this->actions as $a => $action) {
				if (empty($action['transitions'])) {
					continue;
				}
				foreach ($action['transitions'] as $src => $transition) {
					$transition = array_merge($action, $transition);
					if ($src === null || $src === '') {
						$s_src = 'BEGIN';
					} else {
						$s_src = 's_'.$src;
						if (!array_key_exists($src, $this->states)) {
							$missing_states[$src] = true;
						}
					}
					foreach ($transition['targets'] as $dst) {
						if ($dst === null || $dst === '') {
							$s_dst = $src == '' ? 'BEGIN':'END';
							$have_final_state = true;
						} else {
							$s_dst = 's_'.$dst;
							if (!array_key_exists($dst, $this->states)) {
								$missing_states[$dst] = true;
							}
						}
						$edges[] = [
							'start' => $s_src,
							'end' => $s_dst,
							'label' => $a,
							'color' => $transition['color'] ?? "#000",
							'weight' => $transition['weight'] ?? null,
							'arrowTail' => isset($transition['access_policy']) && isset($this->access_policies[$transition['access_policy']]['arrow_tail'])
								? $this->access_policies[$transition['access_policy']]['arrow_tail']
								: null,
						];
					}
				}
			}
		}

		// Missing states
		foreach ($missing_states as $s => $state) {
			$nodes[] = [
				'id' => 's_'.$s,
				'label' => $s."\n(undefined)",
				'color' => "#ff0000",
				'fill' => "#ffeedd",
			];
		}

		// Final state
		if ($have_final_state) {
			$nodes[] = [
				'id' => 'END',
				'shape' => 'uml.final_state',
			];
		}

		// Smalldb state diagram
		$machine_graph = [
			'layout' => 'dagre',
			'nodes' => $nodes,
			'edges' => $edges,
		];

		// Optionaly render machine-specific debug data
		if ($debug_opts) {
			// Return the extended graph
			return $this->exportJsonAddExtras($debug_opts, $machine_graph);
		} else {
			// Return the Smalldb state diagram only
			return $machine_graph;
		}
	}


	/**
	 * Export state machine to Graphviz source code.
	 *
	 * @param $debug_opts Machine-specific debugging options - passed to
	 * 	exportDotRenderDebugData(). If empty/false, no debug data are
	 * 	added to the diagram.
	 */
	public function exportDot($debug_opts = false)
	{
		ob_start();

		// DOT Header
		echo	"#\n",
			"# State machine visualization\n",
			"#\n",
			"# Use \"dot -Tpng this-file.dot -o this-file.png\" to compile.\n",
			"#\n",
			"digraph structs {\n",
			"	rankdir = TB;\n",
			"	bgcolor = transparent;\n",
			"	pad = 0;\n",
			"	margin = 0;\n",
			//"	splines = line;\n",
			"	edge [ arrowtail=none, arrowhead=normal, dir=both, arrowsize=0.6, fontsize=8, fontname=\"sans\" ];\n",
			"	node [ shape=box, style=\"rounded,filled\", fontsize=9, fontname=\"sans\", fillcolor=\"#eeeeee\" ];\n",
			"	graph [ fontsize=9, fontname=\"sans bold\" ];\n",
			"\n";

		// Wrap state diagram into subgraph if there are extras to avoid mixing of them together
		if (!empty($this->state_diagram_extras)) {
			echo "\tsubgraph cluster_main {\n",
				"\t\t", "graph [ margin = 10 ];\n",
				"\t\t", "color = transparent;\n\n";
		}

		// Start state
		echo "\t\t", "BEGIN [",
			"id = \"BEGIN\",",
			"label = \"\",",
			"shape = circle,",
			"color = black,",
			"fillcolor = black,",
			"penwidth = 0,",
			"width = 0.25,",
			"style = filled",
			"];\n";

		// States
		echo "\t\t", "node [ shape=ellipse, fontsize=9, style=\"filled\", fontname=\"sans\", fillcolor=\"#eeeeee\", penwidth=2 ];\n";
		$group_content = array();
		if (!empty($this->states)) {
			foreach ($this->states as $s => $state) {
				if ($s != '') {
					$id = $this->exportDotIdentifier($s);
					echo "\t\t", $id;
					//echo " [ label=\"", addcslashes(empty($state['label']) ? $s : $state['label'], '"'), "\"";
					echo " [ id = \"", addcslashes($id, '"'), "\", label=\"", addcslashes($s, '"'), "\"";
					if (!empty($state['color'])) {
						echo ", fillcolor=\"", addcslashes($state['color'], '"'), "\"";
					}
					echo " ];\n";
				}

				if (isset($state['group'])) {
					$group_content[$state['group']][] = $s;
				}
			}
		}
		echo "\n";

		// State groups
		if (!empty($this->state_groups)) {
			$this->exportDotRenderGroups($this->state_groups, $group_content);
		}

		$have_final_state = false;
		$missing_states = array();

		// Transitions
		$used_actions = array();
		if (!empty($this->actions)) {
			foreach ($this->actions as $a => $action) {
				if (empty($action['transitions'])) {
					continue;
				}
				foreach ($action['transitions'] as $src => $transition) {
					$transition = array_merge($action, $transition);
					if ($src === null || $src === '') {
						$s_src = 'BEGIN';
					} else {
						$s_src = $this->exportDotIdentifier($src);
						if (!array_key_exists($src, $this->states)) {
							$missing_states[$src] = true;
						}
					}
					foreach ($transition['targets'] as $dst) {
						if ($dst === null || $dst === '') {
							$s_dst = $src == '' ? 'BEGIN':'END';
							$have_final_state = true;
						} else {
							$s_dst = $this->exportDotIdentifier($dst);
							if (!array_key_exists($dst, $this->states)) {
								$missing_states[$dst] = true;
							}
						}
						echo "\t\t", $s_src, " -> ", $s_dst, " [ ";
						echo "label=\" ", addcslashes($a, '"'), "  \"";
						if (!empty($transition['color'])) {
							echo ", color=\"", addcslashes($transition['color'], '"'), "\"";
							echo ", fontcolor=\"", addcslashes($transition['color'], '"'), "\"";
						}
						if (isset($transition['weight'])) {
							echo ", weight=", (int) $transition['weight'];
						}
						if (isset($transition['access_policy']) && isset($this->access_policies[$transition['access_policy']]['arrow_tail'])) {
							$arrow_tail = $this->access_policies[$transition['access_policy']]['arrow_tail'];
							echo ", arrowtail=\"", addcslashes($arrow_tail, '"'), "\"";
						}
						echo " ];\n";
					}
				}
			}
		}

		// Missing states
		foreach ($missing_states as $s => $state) {
			echo "\t\t", $this->exportDotIdentifier($s), " [ label=\"", addcslashes($s, '"'), "\\n(undefined)\", fillcolor=\"#ffccaa\" ];\n";
		}

		// Final state
		if ($have_final_state) {
			echo "\n\t\t", "END [",
				" id = \"END\",",
				" label = \"\",",
				" shape = doublecircle,",
				" color = black,",
				" fillcolor = black,",
				" penwidth = 1.8,",
				" width = 0.20,",
				" style = filled",
				" ];\n";
		}

		// Close state diagram subgraph
		if (!empty($this->state_diagram_extras)) {
			echo "\t}\n";
		}

		// Optionaly render machine-specific debug data
		if ($debug_opts) {
			$this->exportDotRenderExtras($debug_opts);
		}
		// DOT Footer
		echo "}\n";

		return ob_get_clean();
	}


	/**
	 * Convert state machine state name or group name to a safe dot identifier
	 * FIXME: Extract Graphviz library to keep graphs and parts of the graphs contained.
	 *
	 * @param string $str  Unsafe generic identifier
	 * @param string $prefix  Prefix for the Dot identifier
	 * @return string  Dot identifier
	 */
	public static function exportDotIdentifier(string $str, string $prefix = 's_')
	{
		return $prefix.preg_replace('/[^a-zA-Z0-9_]+/', '_', $str).'_'.dechex(0xffff & crc32($str));
	}


	/**
	 * Recursively render groups in state machine diagram.
	 */
	private function exportDotRenderGroups($groups, $group_content, $indent = "\t") {
		foreach ($groups as $g => $group) {
			echo $indent, "subgraph ", $this->exportDotIdentifier($g, 'cluster_'), " {\n";
			if (isset($group['label'])) {
				echo $indent, "\t", "label = \"", addcslashes($group['label'], '"'), "\";\n";
			}
			if (!empty($group['color'])) {
				echo $indent, "\t", "color=\"", addcslashes($group['color'], '"'), "\";\n";
				echo $indent, "\t", "fontcolor=\"", addcslashes($group['color'], '"'), "\";\n";
			} else {
				// This cannot be defined globally, since nested groups inherit the settings.
				echo $indent, "\t", "color=\"#666666\";\n";
				echo $indent, "\t", "fontcolor=\"#666666\";\n";
			}
			foreach ($group_content[$g] as $s) {
				echo $indent, "\t", $this->exportDotIdentifier($s), ";\n";
			}
			if (isset($group['groups'])) {
				$this->exportDotRenderGroups($group['groups'], $group_content, "\t".$indent);
			}
			echo $indent, "}\n";
		}
	}


	/**
	 * Render extra diagram features.
	 *
	 * Writes Graphviz dot syntax to stdout (echo), output is placed just
	 * before digraph's closing bracket.
	 *
	 * @param $debug_opts Machine-specific Options to configure visible
	 * 	debug data.
	 */
	protected function exportDotRenderExtras($debug_opts)
	{
		if (!empty($this->state_diagram_extras)) {
			echo "\tsubgraph cluster_extras {\n",
				"\t\tgraph [ margin = 10; ];\n",
				"\t\tcolor=transparent;\n\n";
			foreach($this->state_diagram_extras as $i => $e) {
				echo "\n\t# Extras ", str_replace("\n", " ", $i), "\n";
				echo $e, "\n";
			}
			echo "\t}\n";
		}
	}


	/**
	 * Add extra diagram features into the diagram.
	 *
	 * Extend this method to add debugging visualizations.
	 *
	 * @see AbstractMachine::exportJson().
	 * @see https://grafovatko.smalldb.org/
	 *
	 * @param $debug_opts Machine-specific Options to configure visible
	 * 	debug data.
	 * @param $machine_graph Smalldb state chart wrapped in a 'smalldb' node.
	 */
	protected function exportJsonAddExtras($debug_opts, $machine_graph)
	{
		if (!empty($this->state_diagram_extras_json)) {
			$graph = $this->state_diagram_extras_json;
			if (!isset($graph['layout'])) {
				$graph['layout'] = 'row';
				$graph['layoutOptions'] = [
					'align' => 'top',
				];
			}
			if (!isset($graph['nodes'])) {
				$graph['nodes'] = [];
			}
			if (!isset($graph['edges'])) {
				$graph['edges'] = [];
			}
			array_unshift($graph['nodes'], [
					'id' => 'smalldb',
					'label' => $this->machine_type,
					'graph' => $machine_graph,
					'color' => '#aaa',
					'x' => 0,
					'y' => 0,
				]);
			return $graph;
		} else {
			return $machine_graph;
		}
	}

	/** @} */

}

