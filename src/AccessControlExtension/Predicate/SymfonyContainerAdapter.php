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

use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;


class SymfonyContainerAdapter implements ContainerAdapter
{
	protected const SERVICE_PREFIX = 'smalldb.guard.predicate_';
	private static int $n = 1;
	private ContainerBuilder $container;


	public function __construct(ContainerBuilder $container)
	{
		$this->container = $container;
	}


	/**
	 * @param ContainerBuilder $container
	 * @return Reference
	 */
	public function registerService(?string $id, string $className, ?array $args = null)
	{
		$realId = $id ?? static::SERVICE_PREFIX . (static::$n++);
		$definition = $this->container->autowire($realId, $className);
		if ($args !== null) {
			$definition->setArguments($args);
		}
		return new Reference($realId);
	}


	/**
	 * @param Reference $service
	 * @return ServiceClosureArgument
	 */
	public function closureWrap($service)
	{
		return new ServiceClosureArgument($service);
	}

}
