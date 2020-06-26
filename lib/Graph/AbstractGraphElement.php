<?php declare(strict_types = 1);
/*
 * Copyright (c) 2018, Josef Kufner  <josef@kufner.cz>
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


namespace Smalldb\Graph;


abstract class AbstractGraphElement extends AbstractElement
{
	/**
	 * @var string
	 */
	private $id;

	/**
	 * @var NestedGraph
	 */
	private $graph;


	public function __construct(NestedGraph $graph, string $id, array $attrs)
	{
		parent::__construct($attrs);
		$this->graph = $graph;
		$this->id = $id;
	}


	public function getId(): string
	{
		return $this->id;
	}


	public function getGraph(): NestedGraph
	{
		return $this->graph;
	}


	public function getRootGraph(): Graph
	{
		return $this->graph->getRootGraph();
	}


}
