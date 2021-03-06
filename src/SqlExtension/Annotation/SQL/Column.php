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

namespace Smalldb\StateMachine\SqlExtension\Annotation\SQL;

use Smalldb\StateMachine\Definition\Builder\PropertyPlaceholder;
use Smalldb\StateMachine\Definition\AnnotationReader\ApplyToPropertyPlaceholderInterface;
use Smalldb\StateMachine\SqlExtension\AnnotationException;
use Smalldb\StateMachine\SqlExtension\Definition\SqlPropertyExtensionPlaceholder;


/**
 * SQL column annotation -- the property is mapped 1:1 to an SQL column.
 *
 * @Annotation
 * @Target({"PROPERTY"})
 */
class Column implements ApplyToPropertyPlaceholderInterface
{
	public string $name;

	/**
	 * Ignored; for compatibility with the Doctrine annotation.
	 */
	public string $type;

	/**
	 * Ignored; for compatibility with the Doctrine annotation.
	 */
	public bool $unique;


	public function applyToPropertyPlaceholder(PropertyPlaceholder $propertyPlaceholder): void
	{
		/** @var SqlPropertyExtensionPlaceholder $sqlExtensionPlaceholder */
		$sqlExtensionPlaceholder = $propertyPlaceholder->getExtensionPlaceholder(SqlPropertyExtensionPlaceholder::class);

		if ($sqlExtensionPlaceholder->sqlSelect !== null) {
			throw new AnnotationException("Property {$propertyPlaceholder->name}: Annotations Column and Select are mutually exclusive.");
		}

		$sqlExtensionPlaceholder->sqlColumn = $this->name ?? $propertyPlaceholder->name;
	}

}
