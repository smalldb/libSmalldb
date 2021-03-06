<?php declare(strict_types = 1);
//
// Generated by Smalldb\CodeCooker\Generator\DtoGenerator.
// Do NOT edit! All changes will be lost!
// 
// 
namespace Smalldb\StateMachine\Test\Example\User\UserProfileData;

use InvalidArgumentException;
use Smalldb\CodeCooker\Annotation\GeneratedClass;
use Smalldb\StateMachine\Test\Example\User\UserProfileProperties as Source_UserProfileProperties;


/**
 * @GeneratedClass
 * @see \Smalldb\StateMachine\Test\Example\User\UserProfileProperties
 */
class UserProfileDataMutable extends Source_UserProfileProperties implements UserProfileData
{

	public function __construct(?UserProfileData $source = null)
	{
		if ($source !== null) {
			if ($source instanceof Source_UserProfileProperties) {
				$this->fullName = $source->fullName;
				$this->email = $source->email;
			} else {
				$this->fullName = $source->getFullName();
				$this->email = $source->getEmail();
			}
		}
	}


	public static function fromArray(?array $source, ?UserProfileData $sourceObj = null): ?self
	{
		if ($source === null) {
			return null;
		}
		$t = $sourceObj instanceof self ? clone $sourceObj : new self($sourceObj);
		$t->fullName = (string) $source['fullName'];
		$t->email = (string) $source['email'];
		return $t;
	}


	public static function fromIterable(?UserProfileData $sourceObj, iterable $source): self
	{
		$t = $sourceObj instanceof self ? clone $sourceObj : new self($sourceObj);
		foreach ($source as $prop => $value) {
			switch ($prop) {
				case 'fullName': $t->fullName = $value; break;
				case 'email': $t->email = $value; break;
				default: throw new InvalidArgumentException('Unknown property: "' . $prop . '" not in ' . __CLASS__);
			}
		}
		return $t;
	}


	public function getFullName(): string
	{
		return $this->fullName;
	}


	public function getEmail(): string
	{
		return $this->email;
	}


	public static function get(UserProfileData $source, string $propertyName)
	{
		switch ($propertyName) {
			case 'fullName': return $source->getFullName();
			case 'email': return $source->getEmail();
			default: throw new \InvalidArgumentException("Unknown property: " . $propertyName);
		}
	}


	public function setFullName(string $fullName): void
	{
		$this->fullName = $fullName;
	}


	public function setEmail(string $email): void
	{
		$this->email = $email;
	}

}

