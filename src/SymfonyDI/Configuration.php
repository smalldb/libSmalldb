<?php
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
namespace Smalldb\StateMachine\SymfonyDI;

use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;


class Configuration implements ConfigurationInterface
{

	public function getConfigTreeBuilder()
	{
		// @formatter:off
		$treeBuilder = new TreeBuilder('smalldb');
		$children = $treeBuilder->getRootNode()->children();

		$children->arrayNode('class_generator')
			->info('Definition bag & References generator')
			->children()
				->scalarNode('namespace')
					->info('Namespace of the generated classes')
					->defaultValue('Smalldb\\GeneratedCode\\')
					->cannotBeEmpty()
				->end()
				->scalarNode('path')
					->info('Directory where generated PHP files will be stored')
					->defaultValue('%kernel.cache_dir%/smalldb')
					->cannotBeEmpty()
				->end()
			->end();

		$children->arrayNode('definition_classes')
			->info('How to find state machine definitions?')
			->children()
				->booleanNode('use_composer')
					->info('Use composer autoloader configuration to scan all classes.')
					->defaultTrue()
				->end()
				->booleanNode('ignore_vendor_dir')
					->info('Do not scan classes inside vendor directory (see use_composer)')
					->defaultTrue()
				->end()
				->arrayNode('class_list')
					->info('List of additional state machine definitions')
					->scalarPrototype()->end()
				->end()
				->arrayNode('psr4_dirs')
					->info('PSR-4 directories with state machine definitions (map: namespace => directory)')
					->useAttributeAsKey('name')
					->scalarPrototype()->end()
				->end()
				->arrayNode('exclude_dirs')
					->info('List of directories which should not be scanned')
					->scalarPrototype()->end()
				->end()
			->end();

		$this->addNodes($children);
		// @formatter:on

		return $treeBuilder;
	}


	protected function addNodes(NodeBuilder $children)
	{
		// Smalldb Bundle adds more nodes here.
	}

}

