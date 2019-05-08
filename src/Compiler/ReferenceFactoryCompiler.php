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

use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\Utils\PhpFileWriter;

/**
 * Compile a reference factory from all registered backends.
 */
class ReferenceFactoryCompiler
{

	/**
	 * @var Smalldb
	 */
	protected $smalldb;


	/**
	 * Compiler constructor.
	 */
	public function __construct(Smalldb $smalldb)
	{
		$this->smalldb = $smalldb;
	}


	/**
	 * Compile
	 */
	public function compile(string $dest_dir)
	{
		$php = new PhpFileWriter($dest_dir.'/ReferenceFactory.php');
		$php->fileHeader(__CLASS__);
		$php->namespace("Smalldb\\Generated");
		$php->beginClass('ReferenceFactory');

		$php->writeln("protected \$smalldb;");

		$php->beginFinalMethod('__construct', ["\\Smalldb\\StateMachine\\Smalldb \$smalldb"]);
		{
			$php->writeln("\$this->smalldb = \$smalldb;");
		}
		$php->endMethod();

		$php->beginFinalMethod('__invoke', ["...\$args"], "\\Smalldb\\StateMachine\\Reference");
		{
			$php->writeln("return \$this->smalldb->ref(...\$args);");
		}
		$php->endMethod();

		foreach($this->smalldb->getAllMachines() as $m => $machine) {
			$id = $machine->describeId();
			$idArgs = array_map(function($a) { return "\$$a"; }, $id);
			$php->beginMethod($m, $idArgs, "\\Smalldb\\StateMachine\\Reference");
			{
				$php->writeln("return \$this->smalldb->ref(%s, " . join(", ", $idArgs) . ");", $m);
			}
			$php->endMethod();
		}

		$php->endClass();
		$php->close();
	}

}
