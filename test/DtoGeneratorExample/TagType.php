<?php declare(strict_types = 1);
/*
 * Copyright (c) 2020, Josef Kufner  <josef@kufner.cz>
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

namespace Smalldb\StateMachine\Test\DtoGeneratorExample;

use Smalldb\StateMachine\Test\DtoGeneratorExample\Tag\Tag;
use Smalldb\StateMachine\Test\DtoGeneratorExample\Tag\TagFormDataMapper;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class TagType extends AbstractType
{

	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$builder->add('id', IntegerType::class, [
			'attr' => ['autofocus' => true],
			'label' => 'ID',
		]);
		$builder->add('name', TextType::class, [
			'label' => 'Name',
		]);

		$builder->setDataMapper(new TagFormDataMapper());
	}


	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefault('empty_data', null);
		$resolver->setDefault('data_class', Tag::class);
	}

}
