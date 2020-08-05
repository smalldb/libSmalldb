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

namespace Smalldb\StateMachine\Test;

use Smalldb\StateMachine\Definition\Builder\PreprocessorList;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilder;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilderFactory;
use Smalldb\StateMachine\Definition\Renderer\StateMachineExporter;
use Smalldb\StateMachine\StyleExtension\Definition\GraphLayoutExtension;
use Smalldb\StateMachine\StyleExtension\Definition\GraphLayoutExtensionPlaceholder;
use Smalldb\StateMachine\StyleExtension\Definition\StyleExtension;
use Smalldb\StateMachine\StyleExtension\Definition\StyleExtensionPlaceholder;


class StyleExtensionTest extends TestCase
{

	public function testColor()
	{
		$placeholder = new StyleExtensionPlaceholder();
		$placeholder->color = 'blue';
		$ext = $placeholder->buildExtension();
		$color = $ext->getColor();
		$this->assertEquals('blue', $color);
	}


	public function testNoColor()
	{
		$placeholder = new StyleExtensionPlaceholder();
		$ext = $placeholder->buildExtension();
		$this->assertNull($ext);
	}


	public function testNoLayout()
	{
		$placeholder = new GraphLayoutExtensionPlaceholder();
		$ext = $placeholder->buildExtension();
		$this->assertNull($ext);
	}


	public function testRender()
	{
		$builder = StateMachineDefinitionBuilderFactory::createDefaultFactory()->createDefinitionBuilder();
		$builder->setMachineType('item');
		$t = $builder->addTransition('create', '', ['Exists']);
		$s = $builder->addState('Exists');

		foreach ([$s, $t] as $d) {
			/** @var StyleExtensionPlaceholder $extP */
			$extP = $d->getExtensionPlaceholder(StyleExtensionPlaceholder::class);
			$extP->color = "#baf";
		}

		/** @var GraphLayoutExtensionPlaceholder $gExtP */
		$gExtP = $builder->getExtensionPlaceholder(GraphLayoutExtensionPlaceholder::class);
		$layout = $gExtP->layout = "column";
		$layoutOptions = $gExtP->layoutOptions = ["sortNodes" => true];

		$definition = $builder->build();

		/** @var StyleExtension[] $exts */
		$exts = [
			$definition->getState('Exists')->getExtension(StyleExtension::class),
			$definition->getTransition('create', '')->getExtension(StyleExtension::class),
		];
		foreach ($exts as $ext) {
			$this->assertEquals("#baf", $ext->getColor());
		}

		/** @var GraphLayoutExtension $gExt */
		$gExt = $definition->getExtension(GraphLayoutExtension::class);
		$this->assertEquals($layout, $gExt->getLayout());
		$this->assertEquals($layoutOptions, $gExt->getLayoutOptions());

		$exporter = new StateMachineExporter($definition);
		$grafovatkoData = $exporter->export();

		$this->assertEquals($layout, $grafovatkoData['layout']);
		$this->assertEquals($layoutOptions, $grafovatkoData['layoutOptions']);

		$this->assertEquals("Exists", $grafovatkoData['nodes'][0]['attrs']['label']);
		$this->assertEquals("#baf", $grafovatkoData['nodes'][0]['attrs']['fill']);

		$this->assertEquals("create", $grafovatkoData['edges'][0]['attrs']['label']);
		$this->assertEquals("#baf", $grafovatkoData['edges'][0]['attrs']['color']);
	}

}
