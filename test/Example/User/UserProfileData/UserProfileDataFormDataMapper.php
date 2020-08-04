<?php declare(strict_types = 1);
//
// Generated by Smalldb\CodeCooker\Generator\DtoGenerator.
// Do NOT edit! All changes will be lost!
// 
// 
namespace Smalldb\StateMachine\Test\Example\User\UserProfileData;

use Smalldb\CodeCooker\Annotation\GeneratedClass;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * @GeneratedClass
 * @see \Smalldb\StateMachine\Test\Example\User\UserProfileProperties
 */
class UserProfileDataFormDataMapper implements DataMapperInterface
{

	public function mapDataToForms($viewData, iterable $forms)
	{
		if ($viewData === null) {
			return;
		} else if ($viewData instanceof UserProfileData) {
			foreach ($forms as $prop => $field) {
				$field->setData(UserProfileDataImmutable::get($viewData, $prop));
			}
		} else {
			throw new UnexpectedTypeException($viewData, UserProfileDataImmutable::class);
		}
	}


	public function mapFormsToData(iterable $forms, & $viewData)
	{
		$viewData = UserProfileDataImmutable::fromIterable($viewData, (function() use ($forms) { foreach($forms as $k => $field) yield $k => $field->getData(); })());
	}


	public function configureOptions(OptionsResolver $optionsResolver)
	{
		$optionsResolver->setDefault("empty_data", null);
		$optionsResolver->setDefault("data_class", UserProfileData::class);
	}

}
