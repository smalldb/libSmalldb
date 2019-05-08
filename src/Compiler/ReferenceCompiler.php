<?php declare(strict_types = 1);
/*
 * Copyright (c) 2017-2019, Josef Kufner  <josef@kufner.cz>
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

namespace Smalldb\StateMachine\Compiler;

use Smalldb\StateMachine\AbstractMachine;
use Smalldb\StateMachine\Utils\PhpFileWriter;

/**
 * Compile a reference to a state machine
 */
class ReferenceCompiler
{

	/**
	 * @var AbstractMachine
	 */
	protected $machine;


	/**
	 * Compiler constructor.
	 */
	public function __construct(AbstractMachine $machine)
	{
		$this->machine = $machine;
	}


	/**
	 * Compile
	 */
	public function compile(string $dest_dir)
	{
		$php = new PhpFileWriter();
		$machine_name = $this->machine->getMachineType();
		$ref_class_name = $php->toCamelCase($machine_name) . 'Reference';
		$php->open("$dest_dir/$ref_class_name.php");
		$php->fileHeader(__CLASS__);
		$php->namespace("Smalldb\\Generated");
		$php->beginAbstractClass("$ref_class_name extends \\Smalldb\\StateMachine\\Reference");

		$php->beginFinalMethod('__construct', ["\\" . get_class($this->machine) . ' $machine', '$id = null']);
		{
			$php->writeln("parent::__construct(\$machine, \$id);");
		}
		$php->endMethod();

		$r = new \ReflectionClass($this->machine);

		// Transition invocation methods
		foreach ($this->machine->describeAllMachineActions() as $action_name => $action) {
			$m = $r->getMethod($action_name);    // Fixme: Use correct method name
			$params = array_map(function ($p) {
					return ($p->hasType() ? $p->getType().' ' : '')
						. '$' . $p->getName()
						. ($p->isDefaultValueAvailable()
							? ($p->isDefaultValueConstant()
								? ' = '.$p->getDefaultValueConstantName()
								: ' = '.$p->getDefaultValue())
							: '');
				}, $m->getParameters());

			$php->beginFinalMethod($action_name, $params, $m->hasReturnType() ? $m->getReturnType() : '');
			{
				$php->writeln("return \$this->__call(%s, " . join(', ', $params) . ");", $action_name);
			}
			$php->endMethod();
		}

		// Property getters
		foreach ($this->machine->describeAllMachineProperties() as $property_name => $property) {
			$getter_name = 'get' . $php->toCamelCase($property_name);
			$php->beginFinalMethod($getter_name, []);
			{
				$php->beginBlock("if (\$this->properties_cache === null)");
				{
					$php->writeln("\$this->properties_cache = \$this->machine->getProperties(\$this->id);");
				}
				$php->endBlock();
				$php->writeln("return \$this->properties_cache[%s];", $property_name);
			}
			$php->endMethod();
		}

		$php->endClass();
		$php->close();
	}

}
