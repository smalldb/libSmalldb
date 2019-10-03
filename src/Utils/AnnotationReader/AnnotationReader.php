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

namespace Smalldb\StateMachine\Utils\AnnotationReader;

use Doctrine\Common\Annotations\AnnotationReader as DoctrineAnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\DocParser;


class AnnotationReader extends DoctrineAnnotationReader implements AnnotationReaderInterface
{

	public function __construct(DocParser $parser = null)
	{
		parent::__construct($parser);

		// Doxygen annotations
		$this->addGlobalIgnoredName('copydoc');
		$this->addGlobalIgnoredName('htmlinclude');
		$this->addGlobalIgnoredName('implements');
		$this->addGlobalIgnoredName('link');
		$this->addGlobalIgnoredName('note');
		$this->addGlobalIgnoredName('par');
		$this->addGlobalIgnoredName('see');
		$this->addGlobalIgnoredName('since');
		$this->addGlobalIgnoredName('throws');
		$this->addGlobalIgnoredName('var');
		$this->addGlobalIgnoredName('warning');

		// Use autoloader to load annotations
		if (class_exists(AnnotationRegistry::class)) {
			AnnotationRegistry::registerUniqueLoader('class_exists');
		}
	}

}
