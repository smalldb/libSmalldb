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


namespace Smalldb\StateMachine\Test;


class BpmnBenchmarkTest extends BpmnTest
{

	/** @var int Minimum number of tasks to generate */
	const MIN_N = 9;

	/** @var int Maximum number of tasks to generate */
	const MAX_N = 20000;

	/** @var int Fraction of N to add each step ($N += $N / N_STEP_FRACTION) */
	const N_STEP_FRACTION = 3;

	/** @var int Minimum increment of $N */
	const N_MIN_STEP = 200;

}
