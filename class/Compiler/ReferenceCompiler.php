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

namespace Smalldb\StateMachine\Compiler;

use Smalldb\StateMachine\AbstractMachine;

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
		$machine_name = $this->machine->getMachineType();
		$ref_class_name = str_replace('_', '', ucwords($machine_name, '_')) . 'Reference';

		$php = new PhpFileWriter("$dest_dir/$ref_class_name.php");
		$php->fileHeader(__CLASS__);

		$php->namespace("Smalldb\\Generated");
		$php->beginClass("$ref_class_name extends \\Smalldb\\StateMachine\\Reference");

		$php->beginMethod('__construct', ["\\" . get_class($this->machine) . ' $machine', '$id = null']);
		$php->writeln("parent::__construct(\$machine, \$id);");
		$php->endMethod();

		$r = new \ReflectionClass($this->machine);

		foreach ($this->machine->getAllMachineActions() as $action) {
			$m = $r->getMethod($action);    // Fixme: Use correct method name
			$params = array_map(function ($p) {
					return ($p->hasType() ? $p->getType().' ' : '')
						. '$' . $p->getName()
						. ($p->isDefaultValueAvailable()
							? ($p->isDefaultValueConstant()
								? ' = '.$p->getDefaultValueConstantName()
								: ' = '.$p->getDefaultValue())
							: '');
				}, $m->getParameters());
			$php->beginMethod($action, $params, $m->hasReturnType() ? $m->getReturnType() : '');
			$php->writeln("return \$this->__call('$action', ".join(', ', $params).");");
			$php->endMethod();
		}

		$php->endClass();

		$php->close();
	}

}
