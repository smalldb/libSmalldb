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


namespace Smalldb\StateMachine\Test\Example\TestTemplate;


use Smalldb\StateMachine\Test\BpmnTest;

class NavigationTemplate implements Template
{
	private $activeFile = null;

	public function setActiveUrl(?string $url): self
	{
		$this->activeFile = $url ? basename($url) : null;
		return $this;
	}

	public function render(): string
	{
		return Html::nav([],
			Html::ul([],
				$this->item('index.html', 'CRUD Item'),
				...$this->bpmnItems()
			));
	}

	private function item($url, $label)
	{
		return Html::li(['class' => $this->activeFile == basename($url) ? 'active' : null],
			Html::a(['href' => $url],
			Html::text($label)));
	}

	private function bpmnItems()
	{
		$test = new BpmnTest();
		foreach ($test->bpmnFileProvider() as [$bpmnFilename, $svgFilename]) {
			$basename = basename($bpmnFilename);
			yield $this->item("$basename.html", $basename);
		}
	}

}
