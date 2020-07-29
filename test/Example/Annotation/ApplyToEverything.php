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

namespace Smalldb\StateMachine\Test\Example\Annotation;

use Smalldb\StateMachine\Definition\AnnotationReader\ApplyToActionPlaceholderInterface;
use Smalldb\StateMachine\Definition\AnnotationReader\ApplyToPlaceholderInterface;
use Smalldb\StateMachine\Definition\AnnotationReader\ApplyToPropertyPlaceholderInterface;
use Smalldb\StateMachine\Definition\AnnotationReader\ApplyToStateMachineBuilderInterface;
use Smalldb\StateMachine\Definition\AnnotationReader\ApplyToStatePlaceholderInterface;
use Smalldb\StateMachine\Definition\AnnotationReader\ApplyToTransitionPlaceholderInterface;
use Smalldb\StateMachine\Definition\AnnotationReader\ReflectionClassAwareAnnotationInterface;
use Smalldb\StateMachine\Definition\AnnotationReader\ReflectionConstantAwareAnnotationInterface;
use Smalldb\StateMachine\Definition\AnnotationReader\ReflectionMethodAwareAnnotationInterface;
use Smalldb\StateMachine\Definition\AnnotationReader\ReflectionPropertyAwareAnnotationInterface;
use Smalldb\StateMachine\Definition\Builder\ActionPlaceholder;
use Smalldb\StateMachine\Definition\Builder\ExtensiblePlaceholder;
use Smalldb\StateMachine\Definition\Builder\PropertyPlaceholder;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilder;
use Smalldb\StateMachine\Definition\Builder\StatePlaceholder;
use Smalldb\StateMachine\Definition\Builder\TransitionPlaceholder;


/**
 * @Annotation
 *
 * This annotation remembers when it was applied to something.
 * Used by AnnotationReader tests.
 */
class ApplyToEverything implements
	ApplyToPlaceholderInterface,
	ApplyToStateMachineBuilderInterface,
	ApplyToStatePlaceholderInterface,
	ApplyToActionPlaceholderInterface,
	ApplyToTransitionPlaceholderInterface,
	ApplyToPropertyPlaceholderInterface,
	ReflectionClassAwareAnnotationInterface,
	ReflectionConstantAwareAnnotationInterface,
	ReflectionMethodAwareAnnotationInterface,
	ReflectionPropertyAwareAnnotationInterface
{

	public static array $calledMethods = [];
	public static array $calledMethodCounter = [];


	public static function resetCounters()
	{
		static::$calledMethods = [];
		$reflection = new \ReflectionClass(__CLASS__);
		foreach ($reflection->getMethods() as $m) {
			if (!$m->isStatic()) {
				static::$calledMethodCounter[__CLASS__ . '::' . $m->getName()] = 0;
			}
		}
	}


	public function applyToPlaceholder(ExtensiblePlaceholder $placeholder): void
	{
		static::$calledMethods[] = [__METHOD__, func_get_args()];
		static::$calledMethodCounter[__METHOD__]++;
	}


	public function applyToBuilder(StateMachineDefinitionBuilder $builder): void
	{
		static::$calledMethods[] = [__METHOD__, func_get_args()];
		static::$calledMethodCounter[__METHOD__]++;
	}


	public function applyToActionPlaceholder(ActionPlaceholder $placeholder): void
	{
		static::$calledMethods[] = [__METHOD__, func_get_args()];
		static::$calledMethodCounter[__METHOD__]++;
	}


	public function applyToPropertyPlaceholder(PropertyPlaceholder $propertyPlaceholder): void
	{
		static::$calledMethods[] = [__METHOD__, func_get_args()];
		static::$calledMethodCounter[__METHOD__]++;
	}


	public function applyToStatePlaceholder(StatePlaceholder $placeholder): void
	{
		static::$calledMethods[] = [__METHOD__, func_get_args()];
		static::$calledMethodCounter[__METHOD__]++;
	}


	public function applyToTransitionPlaceholder(TransitionPlaceholder $placeholder): void
	{
		static::$calledMethods[] = [__METHOD__, func_get_args()];
		static::$calledMethodCounter[__METHOD__]++;
	}


	public function setReflectionClass(\ReflectionClass $reflectionClass)
	{
		static::$calledMethods[] = [__METHOD__, func_get_args()];
		static::$calledMethodCounter[__METHOD__]++;
	}


	public function setReflectionConstant(\ReflectionClassConstant $reflectionConstant)
	{
		static::$calledMethods[] = [__METHOD__, func_get_args()];
		static::$calledMethodCounter[__METHOD__]++;
	}


	public function setReflectionMethod(\ReflectionMethod $reflectionMethod)
	{
		static::$calledMethods[] = [__METHOD__, func_get_args()];
		static::$calledMethodCounter[__METHOD__]++;
	}


	public function setReflectionProperty(\ReflectionProperty $reflectionProperty)
	{
		static::$calledMethods[] = [__METHOD__, func_get_args()];
		static::$calledMethodCounter[__METHOD__]++;
	}

}
