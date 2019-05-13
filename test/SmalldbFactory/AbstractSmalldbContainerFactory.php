<?php declare(strict_types = 1);
/*
 * Copyright (c) 2019, Josef Kufner  <josef@kufner.cz>
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

namespace Smalldb\StateMachine\Test\SmalldbFactory;


use Psr\Container\ContainerInterface;
use Smalldb\StateMachine\Smalldb;
use Smalldb\StateMachine\Test\TestTemplate\TestOutput;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;

abstract class AbstractSmalldbContainerFactory implements SmalldbFactory
{

	protected $out;


	public function __construct()
	{
		$this->out = new TestOutput();
	}


	public function createSmalldb(): Smalldb
	{
		return $this->createContainer()->get(Smalldb::class);
	}


	public function createContainer(): ContainerInterface
	{
		$c = $this->configureContainer(new ContainerBuilder());
		$c->compile();
		$this->dumpContainer($c, preg_replace('/^.*\\\\/', '', get_class($this)));
		return $c;
	}


	abstract protected function configureContainer(ContainerBuilder $c): ContainerBuilder;


	/**
	 * Dump the container so that we can examine it.
	 */
	protected function dumpContainer(ContainerBuilder $c, string $filename): void
	{
		$dumper = new PhpDumper($c);
		$this->out->writeResource($filename, $dumper->dump());
	}

}
