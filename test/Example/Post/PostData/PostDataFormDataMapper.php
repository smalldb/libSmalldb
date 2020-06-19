<?php declare(strict_types = 1);
//
// Generated by Smalldb\StateMachine\CodeGenerator\DtoGenerator.
// Do NOT edit! All changes will be lost!
// 
// 
namespace Smalldb\StateMachine\Test\Example\Post\PostData;

use Smalldb\StateMachine\CodeCooker\Annotation\GeneratedClass;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * @GeneratedClass
 * @see \Smalldb\StateMachine\Test\Example\Post\PostProperties
 */
class PostDataFormDataMapper implements DataMapperInterface
{

	public function mapDataToForms($viewData, iterable $forms)
	{
		if ($viewData === null) {
			return;
		} else if ($viewData instanceof PostData) {
			foreach ($forms as $prop => $field) {
				$field->setData(PostDataImmutable::get($viewData, $prop));
			}
		} else {
			throw new UnexpectedTypeException($viewData, PostDataImmutable::class);
		}
	}


	public function mapFormsToData(iterable $forms, & $viewData)
	{
		$viewData = PostDataImmutable::fromIterable($viewData, $forms, function ($field) { return $field->getData(); });
	}


	public function configureOptions(OptionsResolver $optionsResolver)
	{
		$optionsResolver->setDefault("empty_data", null);
		$optionsResolver->setDefault("data_class", PostData::class);
	}

}

