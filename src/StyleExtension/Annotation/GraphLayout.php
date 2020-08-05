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

namespace Smalldb\StateMachine\StyleExtension\Annotation;

use Smalldb\StateMachine\Definition\Builder\ExtensiblePlaceholder;
use Smalldb\StateMachine\Definition\AnnotationReader\ApplyToPlaceholderInterface;
use Smalldb\StateMachine\StyleExtension\Definition\GraphLayoutExtensionPlaceholder;


/**
 * Layout options for Grafovatko
 *
 * @Annotation
 */
class GraphLayout implements ApplyToPlaceholderInterface
{
	public ?string $layout = null;
	public ?array $layoutOptions = null;


	public function applyToPlaceholder(ExtensiblePlaceholder $placeholder): void
	{
		/** @var GraphLayoutExtensionPlaceholder $ext */
		$ext = $placeholder->getExtensionPlaceholder(GraphLayoutExtensionPlaceholder::class);
		$ext->layout = $this->layout;
		$ext->layoutOptions = $this->layoutOptions;
	}

}
