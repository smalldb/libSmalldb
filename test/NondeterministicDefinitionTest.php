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
namespace Smalldb\StateMachine\Test;

use PHPUnit\Framework\TestCase;
use Smalldb\StateMachine\Definition\ActionDefinition;
use Smalldb\StateMachine\Definition\StateDefinition;
use Smalldb\StateMachine\Definition\StateMachineDefinition;
use Smalldb\StateMachine\Definition\TransitionDefinition;


class NondeterministicDefinitionTest extends TestCase
{

	/**
	 * State machine that rolls a dice.
	 *
	 * [Not Exists]---create--->[Ready]---roll--->[DiceN]---next---.
	 *                             ^-------------------------------'
	 */
	private function buildStateMachine(): StateMachineDefinition
	{
		$D = 6;

		// States
		$s = [];
		$s[''] = $sNotExists = new StateDefinition('');
		$s['Ready'] = $sReady = new StateDefinition('Ready');
		$sDice = [];
		for ($d = 1; $d <= $D; $d++) {
			$name = 'Dice' . $d;
			$s[$name] = $sDice[$name] = new StateDefinition($name);
		}

		// Transitions
		$t = [];
		$tNext = [];
		$t[] = $tCreate = new TransitionDefinition('create', $sNotExists, ['Ready' => $sReady]);
		$t[] = $tRoll = new TransitionDefinition('roll', $sReady, $sDice);
		for ($d = 1; $d <= $D; $d++) {
			$name = 'Dice' . $d;
			$src = $sDice[$name];
			$t[] = $tNext[$src->getName()] = new TransitionDefinition('next', $src, ['Ready' => $sReady]);
		}

		// Actions
		$a = [];
		$a[] = new ActionDefinition('create', [$tCreate->getSourceState()->getName() => $tCreate]);
		$a[] = new ActionDefinition('roll', [$tRoll->getSourceState()->getName() => $tRoll]);
		$a[] = new ActionDefinition('next', $tNext);

		// State machine
		$stateMachineDefinition = new StateMachineDefinition('dice', $s, $a, $t, [], []);

		return $stateMachineDefinition;
	}

	public function testDefinition()
	{
		$stateMachineDefinition = $this->buildStateMachine();
		$this->assertInstanceOf(StateMachineDefinition::class, $stateMachineDefinition);
	}

}
