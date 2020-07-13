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
use Smalldb\StateMachine\Definition\Builder\PropertyPlaceholderApplyInterface;
use Smalldb\StateMachine\SqlExtension\AnnotationException;
use Smalldb\StateMachine\SqlExtension\Definition\SqlPropertyExtensionPlaceholder;


/**
 * SQL expression annotation -- the property is read-only and its value
 * is obtained using an SQL expression.
 *
 * @Annotation
 * @Target({"PROPERTY"})
 */
class Select implements PropertyPlaceholderApplyInterface
{
	public string $expression;


	public function applyToPropertyPlaceholder(PropertyPlaceholder $propertyPlaceholder): void
	{
		/** @var \Smalldb\StateMachine\SqlExtension\Definition\SqlPropertyExtensionPlaceholder $sqlExtensionPlaceholder */
		$sqlExtensionPlaceholder = $propertyPlaceholder->getExtensionPlaceholder(SqlPropertyExtensionPlaceholder::class);

		if ($sqlExtensionPlaceholder->sqlColumn !== null) {
			throw new AnnotationException("Property {$propertyPlaceholder->name}: Annotations Select and Column are mutually exclusive.");
		}

		$sqlExtensionPlaceholder->sqlSelect = $this->expression;
	}

}
