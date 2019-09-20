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


namespace Smalldb\StateMachine\Test\TestTemplate;


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
			),
			Html::ul([],
				$this->item('demo_post.html', 'Post'),
				$this->item('demo_tag.html', 'Tag'),
				$this->item('demo_user.html', 'User'),
			),
			Html::ul([],
				$this->item('demo_supervisor-process.html', 'Supervisor Process (GraphML)'),
				$this->item('demo_pizza-delivery.html', 'Pizza Delivery (BPMN)'),
			),
			Html::ul([],
				...$this->bpmnItems()
			),
			Html::ul([],
				$this->item('noodle.html', 'Generated Noodle'),
				$this->item('user-decides.html', 'Generated User Decides'),
				$this->item('machine-decides.html', 'Generated Machine Decides'),
				$this->item('both-decide.html', 'Generated Both Decide'),
				$this->item('t-shape.html', 'Generated T-Shape'),
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
		$fileWeightMap = [
			'SimpleActions.bpmn' => 10,
			'UserDecides.bpmn' => 20,
			'MachineDecides.bpmn' => 30,
			'SimpleSync.bpmn' => 40,
			'PizzaDelivery.bpmn' => 50,
		];

		$test = new BpmnTest();
		$bpmnFiles = iterator_to_array($test->bpmnFileProvider());
		uasort($bpmnFiles, function($a, $b) use ($fileWeightMap) {
			$fa = basename($a[0]);
			$fb = basename($b[0]);
			$wa = $fileWeightMap[$fa] ?? PHP_INT_MAX;
			$wb = $fileWeightMap[$fb] ?? PHP_INT_MAX;
			return $wa !== $wb ? $wa - $wb : strnatcmp($fa, $fb);
		});

		foreach ($bpmnFiles as [$bpmnFilename, $svgFilename]) {
			$basename = basename($bpmnFilename);
			yield $this->item("$basename.html", $basename);
		}
	}

}
