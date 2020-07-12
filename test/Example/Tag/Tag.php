<?php
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

namespace Smalldb\StateMachine\Test\Example\Tag;

use Smalldb\StateMachine\Annotation\State;
use Smalldb\StateMachine\Annotation\StateMachine;
use Smalldb\StateMachine\Annotation\Transition;
use Smalldb\StateMachine\Annotation\UseRepository;
use Smalldb\StateMachine\Annotation\UseTransitions;
use Smalldb\StateMachine\DtoExtension\Annotation\WrapDTO;
use Smalldb\StateMachine\ReferenceInterface;
use Smalldb\StateMachine\SqlExtension\Annotation\SQL;
use Smalldb\StateMachine\Test\Example\Tag\TagData\TagData;
use Smalldb\StateMachine\Test\Example\Tag\TagData\TagDataImmutable;


/**
 * @StateMachine("tag")
 * @UseRepository(TagRepository::class)
 * @UseTransitions(TagTransitions::class)
 * @WrapDTO(TagDataImmutable::class)
 * @SQL\StateSelect("'Exists'")
 */
abstract class Tag implements ReferenceInterface, TagData
{

	/**
	 * @State(color = "#def")
	 */
	const EXISTS = "Exists";

	/**
	 * @Transition("", {"Exists"}, color = "#4a0")
	 */
	abstract public function create(TagData $itemData);

	/**
	 * @Transition("Exists", {"Exists"})
	 */
	abstract public function update(TagData $itemData);

	/**
	 * @Transition("Exists", {""}, color = "#a40")
	 */
	abstract public function delete();

}

