<?php
/*
 * Copyright (c) 2013, Josef Kufner  <jk@frozen-doe.net>
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

use	\Flupdo\Flupdo\Flupdo,
	\Flupdo\Flupdo\FlupdoProxy;

/**
 * %Smalldb Backend which loads state machine definitions from a directory full 
 * of JSON files and GraphML files.
 *
 * JsonDirBackend supports following file types:
 *
 *   - JSON: Raw structure loaded as is. Extensions: `.json`, `.json.php`
 *   - GraphML: Graph created by yEd graph editor. See loadGraphMLFile(). Extensions: `.graphml`
 *
 */
class JsonDirBackend extends AbstractBackend
{
	/**
	 * Name of directory which contains JSON files with state machine definitions.
	 */
	protected $base_dir;

	/**
	 * Static table of known machine types. Inherit this class and replace this
	 * table, or use 'machine_types' option when creating this backend.
	 *
	 * Each record is also passed to AbstractMachine::initializeMachine().
	 */
	protected $machine_type_table = array(
		/* '$machine_name' => array(
		 *     'table' => '$database_table',
		 *     'class' => '\SomeNamespace\Class',
		 *  	// Human-friendly name of the type
		 *  	'name' => 'Foo Bar',
		 *  	// Human-friendly description (one short paragraph, plain text)
		 *  	'desc' => 'Lorem ipsum dolor sit amet, ...',
		 *  	// Name of the file containing full machine definition
		 *  	'src'  => 'example/foo.json',
		 */
	);


	/**
	 * Constructor compatible with cascade resource factory.
	 */
	public function __construct($options, $context, $alias)
	{
		parent::__construct($options, $context, $alias);

		// Get base dir (constants are available)
		$this->base_dir = filename_format($options['base_dir'], array());

		// Load machine definitions from APC cache
		if (!empty($options['cache_disabled'])) {
			$cache_loaded = false;
			$cache_mtime = 0;
			$cache_disabled = true;
		} else {
			$cache_disabled = false;
			$cache_key = __CLASS__.':'.$alias.':'.$this->base_dir;
			$cache_data = apc_fetch($cache_key, $cache_loaded);
			if ($cache_loaded) {
				list($this->machine_type_table, $cache_mtime) = $cache_data;
				//debug_dump($this->machine_type_table, 'Cached @ '.strftime('%F %T', $cache_mtime));
			}
		}

		if (!$cache_loaded					// failed to load cache
			|| filemtime(__FILE__) > $cache_mtime 		// check loader
			|| filemtime($this->base_dir) > $cache_mtime)	// check directory (new/removed files)
		{
			//debug_msg('Machine type table cache miss. Reloading...');
			$this->machine_type_table = array();

			// Scan base dir for machines
			$dh = opendir($this->base_dir);
			if (!$dh) {
				throw new RuntimeException('Cannot open base dir: '.$this->base_dir);
			}
			$graphml = array();
			while (($file = readdir($dh)) !== false) {
				@ list($machine_type, $ext) = explode('.', $file, 2);
				switch ($ext) {
					case 'json':
					case 'json.php':
						$this->machine_type_table[$machine_type] = parse_json_file($this->base_dir.$file);
						break;
				} 
			}
			closedir($dh);
			ksort($this->machine_type_table);

			foreach ($this->machine_type_table as $machine_type => $json_config) {
				$machine_def = & $this->machine_type_table[$machine_type];
				foreach ((array) @ $json_config['include'] as $include) {
					if (is_array($include)) {
						$include_file = $include['file'];
						$include_group = isset($include['group']) ? $include['group'] : null;
					} else {
						$include_file = $include;
						$include_group = null;
					}
					if ($include_file[0] != '/') {
						$include_file = $this->base_dir.$include_file;
					}
					if (preg_match('/\.json\(\.php\)\?$/i', $include_file)) {
						$machine_def = array_replace_recursive(parse_json_file($include_file), $machine_def);
					} else if (preg_match('/\.graphml$/i', $include_file)) {
						$machine_def = array_replace_recursive($this->loadGraphMLFile($include_file, $include_group), $machine_def);
					} else {
						throw new RuntimeException('Unknown file format: '.$include_file);
					}
				}
				unset($machine_def);
			}

			if (!$cache_disabled) {
				apc_store($cache_key, array($this->machine_type_table, time()));
			}
		}
	}


	/**
	 * Creates a listing using given filters.
	 *
	 * TODO: Support complex filtering over multiple machine types.
	 */
	public function createListing($filters)
	{
		$type = $filters['type'];
		$machine = $this->getMachine($type);
		if ($machine === null) {
			throw new InvalidArgumentException('Machine type "'.$type.'" not found.');
		}

		// Do not confuse machine-specific filtering
		unset($filters['type']);

		return $machine->createListing($filters);
	}


	/**
	 * Get all known state machine types.
	 *
	 * Returns array of strings.
	 */
	public function getKnownTypes()
	{
		return array_keys($this->machine_type_table);
	}


	/**
	 * Describe given type without creating an instance of related state
	 * machine. Intended as data source for user interface generators
	 * (menu, navigation, ...).
	 *
	 * Returns machine description as propery-value pairs in array. There
	 * are few well-known property names which should be used if possible.
	 * Any unknown properties will be ignored.
	 *
	 * array(
	 *  	// Human-friendly name of the type
	 *  	'name' => 'Foo Bar',
	 *  	// Human-friendly description (one short paragraph, plain text)
	 *  	'desc' => 'Lorem ipsum dolor sit amet, ...',
	 *  	// Name of the file containing full machine definition
	 *  	'src'  => 'example/foo.json',
	 *  	...
	 * )
	 */
	public function describeType($type)
	{
		return @ $this->machine_type_table[$type];
	}


	/**
	 * Infer type of referenced machine type using lookup table.
	 *
	 * Reference is pair: Table name + Primary key.
	 *
	 * Returns true if decoding is successful, $type and $id are set to
	 * decoded values. Otherwise returns false.
	 *
	 * Since references are global identifier, this method identifies the
	 * type of referenced machine. In simple cases it maps part of ref to
	 * type, in more complex scenarios it may ask database.
	 *
	 * In simple applications ref consists of pair $type and $id, where $id
	 * is uniquie within given $type.
	 *
	 * $aref is array of arguments passed to AbstractBackend::ref().
	 *
	 * $type is string.
	 *
	 * $id is literal or array of literals (in case of compound key).
	 */
	public function inferMachineType($aref, & $type, & $id)
	{
		$len = count($aref);

		if ($len == 0) {
			return false;
		}

		$type = str_replace('-', '_', array_shift($aref));

		if (!isset($this->machine_type_table[$type])) {
			return false;
		}

		switch ($len) {
			case 1: $id = null; break;
			case 2: $id = reset($aref); break;
			default: $id = $aref;
		}

		return true;
	}


	/**
	 * Factory method: Prepare state machine of given type - a model shared
	 * between multiple real statemachines stored in backend. Do not forget
	 * that actual machine is not reachable, you only get this interface.
	 *
	 * This creates only implementation of the machine, not concrete
	 * instance. See AbstractMachine.
	 *
	 * Returns descendant of AbstractMachine or null.
	 */
	protected function createMachine($type)
	{
		$desc = @ $this->machine_type_table[$type];
		if ($desc === null || empty($desc['class'])) {
			return null;
		}

		//debug_msg('Creating machine %s from class: %s', $type, $desc['class']);
		return new $desc['class']($this, $type, $desc, $this->getContext());
	}


	/**
	 * Load state machine definition from GraphML created by yEd graph editor.
	 *
	 * @see http://www.yworks.com/en/products_yed_about.html
	 */
	protected function loadGraphMLFile($graphml_filename, $graphml_group_name = null)
	{
		// Graph
		$keys = array();
		$nodes = array();
		$edges = array();

		// Load GraphML into DOM
		$dom = new \DOMDocument;
		$dom->load($graphml_filename);

		// Prepare XPath query engine
		$xpath = new \DOMXpath($dom);
		$xpath->registerNameSpace('g', 'http://graphml.graphdrawing.org/xmlns');

		// Find group node
		if ($graphml_group_name) {
			$root_graph = null;
			foreach($xpath->query('//g:graph') as $el) {
				foreach($xpath->query('../g:data/*/*/y:GroupNode/y:NodeLabel', $el) as $label_el) {
					$label = trim($label_el->textContent);

					if ($label == $graphml_group_name) {
						$root_graph = $el;
						break 2;
					}
				}
			}
		} else {
			$root_graph = $xpath->query('/g:graphml/g:graph')->item(0);
		}
		if ($root_graph == null) {
			throw new GraphMLException('Graph node not found.');
		}

		// Load keys
		foreach($xpath->query('./g:key[@attr.name][@id]') as $el) {
			$id = $el->attributes->getNamedItem('id')->value;
			$name = $el->attributes->getNamedItem('attr.name')->value;
			//debug_msg("tag> %s => %s", $id, $name);
			$keys[$id] = $name;
		}

		// Load graph properties
		$graph_props = array();
		foreach($xpath->query('./g:data[@key]', $root_graph) as $data_el) {
			$k = $data_el->attributes->getNamedItem('key')->value;
			if (isset($keys[$k])) {
				if ($keys[$k] == 'Properties') {
					// Special handling of machine properties
					$properties = array();
					foreach ($xpath->query('./property[@name]', $data_el) as $property_el) {
						$property_name = $property_el->attributes->getNamedItem('name')->value;
						foreach ($property_el->attributes as $property_attr_name => $property_attr) {
							$properties[$property_name][$property_attr_name] = $property_attr->value;
						}
					}
					$graph_props['properties'] = $properties;
				} else {
					$graph_props[$this->str2key($keys[$k])] = trim($data_el->textContent);
				}
			}
		}
		//debug_dump($graph_props, '$graph_props');

		// Load nodes
		foreach($xpath->query('.//g:node[@id]', $root_graph) as $el) {
			$id = $el->attributes->getNamedItem('id')->value;
			$node_props = array();
			foreach($xpath->query('.//g:data[@key]', $el) as $data_el) {
				$k = $data_el->attributes->getNamedItem('key')->value;
				if (isset($keys[$k])) {
					$node_props[$this->str2key($keys[$k])] = $data_el->textContent;
				}
			}
			$label = $xpath->query('.//y:NodeLabel', $el)->item(0)->textContent;
			if ($label !== null) {
				$node_props['label'] = trim($label);
			}
			$color = $xpath->query('.//y:Fill', $el)->item(0)->attributes->getNamedItem('color')->value;
			if ($color !== null) {
				$node_props['color'] = trim($color);
			}
			//debug_msg("node> %s: \"%s\"", $id, @ $node_props['state']);
			//debug_dump($node_props, '$node_props');
			$nodes[$id] = $node_props;
		}

		// Load edges
		foreach($xpath->query('//g:graph/g:edge[@id][@source][@target]') as $el) {
			$id = $el->attributes->getNamedItem('id')->value;
			$source = $el->attributes->getNamedItem('source')->value;
			$target = $el->attributes->getNamedItem('target')->value;
			$edge_props = array();
			foreach($xpath->query('.//g:data[@key]', $el) as $data_el) {
				$k = $data_el->attributes->getNamedItem('key')->value;
				if (isset($keys[$k])) {
					$edge_props[$this->str2key($keys[$k])] = $data_el->textContent;
				}
			}
			$label = $xpath->query('.//y:EdgeLabel', $el)->item(0)->textContent;
			if ($label !== null) {
				$edge_props['label'] = trim($label);
			}
			$color = $xpath->query('.//y:LineStyle', $el)->item(0)->attributes->getNamedItem('color')->value;
			if ($color !== null) {
				$edge_props['color'] = trim($color);
			}
			//debug_msg("edge> %s: %s -> %s", $id, $source, $target);
			//debug_dump($edge_props, '$edge_props');
			$edges[$id] = array($source, $target, $edge_props);
		}

		// Build machine definition
		$machine = array('_' => "<?php printf('_%c%c}%c',34,10,10);__halt_compiler();?>");

		// Graph properties
		foreach($graph_props as $k => $v) {
			$machine[$k] = $v;
		}

		// Store states
		foreach ($nodes as & $n) {
			if (empty($n['state'])) {
				if (empty($n['label'])) {
					// Skip 'nonexistent' state, it is present by default
					$n['state'] = '';
					continue;
				} else {
					// Use label as state name
					$n['state'] = (string) $n['label'];
				}
			}
			if (empty($n['label'])) {
				$n['label'] = $n['state'];
			}
			$machine['states'][(string) $n['state']] = $n;
		}

		// Store actions and transitions
		foreach ($edges as $e) {
			list($source_id, $target_id, $props) = $e;
			$source = $source_id != '' ? (isset($nodes[$source_id]) ? (string) $nodes[$source_id]['state'] : null) : '';
			$target = $target_id != '' ? (isset($nodes[$target_id]) ? (string) $nodes[$target_id]['state'] : null) : '';
			if ($source === null || $target === null) {
				// Ignore nonexistent nodes
				continue;
			}
			if (@ $props['action'] != '') {
				$action = $props['action'];
			} else if (@ $props['label'] != '') {
				$action = $props['label'];
			} else {
				throw new GraphMLException(sprintf('Missing label at edge "%s" -> "%s".',
					$nodes[$source]['label'], $nodes[$target]['label']));
			}

			$tr = & $machine['actions'][$action]['transitions'][$source];
			foreach ($props as $k => $v) {
				$tr[$k] = $v;
			}
			$tr['targets'][] = $target;
			unset($tr);
		}

		// Sort stuff to keep them in order when file is modified
		asort($machine['states']);
		asort($machine['actions']);
		//debug_dump($machine['states'], 'States');
		//debug_dump($machine['actions'], 'Actions');

		//debug_dump($machine, '$machine');
		return $machine;
	}


	/**
	 * Convert some nice property name to key suitable for JSON file.
	 */
	private function str2key($str)
	{
		return strtr(mb_strtolower($str), ' ', '_');
	}

}

