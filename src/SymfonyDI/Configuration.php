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

		$children->arrayNode('access_control')
			->info('Access control options')
			->children()
				->scalarNode('default_allow')
					->info('Allow all transitions if state machine has no access policies defined')
					->defaultValue(true)
					->treatNullLike(true)
					->cannotBeEmpty()
				->end()
			->end();

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
			->info('List of state machine definition classes. The class_locator is used if this list is empty.')
			->defaultValue([])
			->scalarPrototype()->end()
			->end();

		$children->arrayNode('class_locator')
			->info('How to find classes including those with with state machine definitions?')
			->children()
				->booleanNode('use_composer')
					->info('Use composer autoloader configuration to scan all classes.')
					->defaultTrue()
				->end()
				->booleanNode('ignore_vendor_dir')
					->info('Do not scan classes inside vendor directory (see use_composer)')
					->defaultTrue()
				->end()
				->variableNode('psr4_dirs')
					->info('PSR-4 directories with state machine definitions (map: namespace => directory)')
				->end()
				->arrayNode('include_dirs')
					->info('List of directories which should be scanned (scan all if empty)')
					->scalarPrototype()->end()
				->end()
				->arrayNode('exclude_dirs')
					->info('List of directories which should not be scanned')
					->scalarPrototype()->end()
				->end()
			->end();

		$children->arrayNode('code_cooker')
			->info('How Code Cooker should generate classes?')
			->children()
				->booleanNode('enable')
					->defaultValue('%kernel.debug%')
					->treatNullLike('%kernel.debug%')
				->end()
				->booleanNode('enable_autoloader_generator')
					->defaultValue('%kernel.debug%')
					->treatNullLike('%kernel.debug%')
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

