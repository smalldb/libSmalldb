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

namespace Smalldb\StateMachine\AccessControlExtension\Annotation\AC;

use Smalldb\StateMachine\AccessControlExtension\Definition\StateMachine\AccessControlExtensionPlaceholder;
use Smalldb\StateMachine\AccessControlExtension\Definition\StateMachine\AccessControlPolicy;
use Smalldb\StateMachine\AccessControlExtension\Definition\StateMachine\AccessControlPolicyPlaceholder;
use Smalldb\StateMachine\Definition\AnnotationReader\ApplyToPlaceholderInterface;
use Smalldb\StateMachine\Definition\AnnotationReader\ApplyToStateMachineBuilderInterface;
use Smalldb\StateMachine\Definition\Builder\StateMachineDefinitionBuilder;


/**
 * List of access policies
 *
 * @Annotation
 * @Target({"CLASS"})
 */
class DefinePolicy implements ApplyToStateMachineBuilderInterface
{
	public string $policyName;
	public PredicateAnnotation $predicate;
	public array $nestedAnnotations;

	public function __construct($values)
	{
		$v = $values['value'];
		$this->policyName = array_shift($v);
		$this->predicate = array_shift($v);
		$this->nestedAnnotations = $v;
	}


	public function applyToBuilder(StateMachineDefinitionBuilder $builder): void
	{
		$policyPlaceholder = new AccessControlPolicyPlaceholder();
		$policyPlaceholder->name = $this->policyName;
		$policyPlaceholder->predicate = $this->predicate->buildPredicate();

		foreach ($this->nestedAnnotations as $nestedAnnotation) {
			if ($nestedAnnotation instanceof ApplyToPlaceholderInterface) {
				$nestedAnnotation->applyToPlaceholder($policyPlaceholder);
			}
		}

		/** @var AccessControlExtensionPlaceholder $ext */
		$ext = $builder->getExtensionPlaceholder(AccessControlExtensionPlaceholder::class);
		$ext->addPolicy($policyPlaceholder);
	}

}
