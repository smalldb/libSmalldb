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

namespace Smalldb\StateMachine\Annotation;

use Smalldb\StateMachine\Definition\Builder\StatePlaceholder;
use Smalldb\StateMachine\Definition\Builder\StatePlaceholderApplyInterface;
use Smalldb\StateMachine\Definition\Builder\TransitionPlaceholder;
use Smalldb\StateMachine\Definition\Builder\TransitionPlaceholderApplyInterface;


/**
 * A color attribute of anything.
 *
 * @Annotation
 */
class Color implements StatePlaceholderApplyInterface, TransitionPlaceholderApplyInterface
{
	/** @var string */
	public $color;

	public function applyToStatePlaceholder(StatePlaceholder $placeholder): void
	{
		$placeholder->color = $this->color;
	}

	public function applyToTransitionPlaceholder(TransitionPlaceholder $placeholder): void
	{
		$placeholder->color = $this->color;
	}

}
