<?php declare(strict_types = 1);
/*
 * Copyright (c) 2020, Josef Kufner  <josef@kufner.cz>
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

namespace Smalldb\StateMachine\AccessControlExtension\Predicate;


abstract class AbstractPredicateOperator implements Predicate, PredicateOperator
{
	protected const COMPILED_CLASS_NAME = AbstractPredicateOperatorCompiled::class;

	/** @var Predicate[] */
	protected array $predicates;


	public final function __construct(Predicate ...$predicates)
	{
		$this->predicates = $predicates;
	}


	public function compile(ContainerAdapter $container)
	{
		return $container->registerService(null, static::COMPILED_CLASS_NAME,
			array_map(fn($p) => $p->compile($container), $this->predicates));
	}


	public final function getNestedPredicates(): array
	{
		return $this->predicates;
	}

}
