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
namespace Smalldb\StateMachine\Test\SmalldbFactory;

use Smalldb\StateMachine\SymfonyDI\SmalldbExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;


class YamlDemoContainer extends AbstractSmalldbContainerFactory implements SmalldbFactory
{

	/**
	 * @throws \Exception
	 */
	protected function configureContainer(ContainerBuilder $c): ContainerBuilder
	{
		$c->registerExtension(new SmalldbExtension());

		$fileLocator = new FileLocator(__DIR__);
		$loader = new YamlFileLoader($c, $fileLocator);
		$loader->load('YamlDemoContainer.yml');

		$c->setParameter('kernel.debug', true);
		$c->setParameter('kernel.project_dir', dirname(dirname(__DIR__)));
		$c->setParameter('generated_output_dir', $this->out->mkdir('generated'));
		return $c;
	}

}
