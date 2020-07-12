<?php declare(strict_types = 1);
//
// Generated by Smalldb\CodeCooker\Generator\DtoGenerator.
// Do NOT edit! All changes will be lost!
// 
// 
namespace Smalldb\StateMachine\Test\Example\User\UserData;

use InvalidArgumentException;
use Smalldb\CodeCooker\Annotation\GeneratedClass;
use Smalldb\StateMachine\Test\Example\User\UserProperties as Source_UserProperties;


/**
 * @GeneratedClass
 * @see \Smalldb\StateMachine\Test\Example\User\UserProperties
 */
class UserDataImmutable extends Source_UserProperties implements UserData
{

	public function __construct(?UserData $source = null)
	{
		if ($source !== null) {
			if ($source instanceof Source_UserProperties) {
				$this->id = $source->id;
				$this->fullName = $source->fullName;
				$this->username = $source->username;
				$this->email = $source->email;
				$this->password = $source->password;
				$this->roles = $source->roles;
			} else {
				$this->id = $source->getId();
				$this->fullName = $source->getFullName();
				$this->username = $source->getUsername();
				$this->email = $source->getEmail();
				$this->password = $source->getPassword();
				$this->roles = $source->getRoles();
			}
		}
	}


	public static function fromArray(?array $source, ?UserData $sourceObj = null): ?self
	{
		if ($source === null) {
			return null;
		}
		$t = $sourceObj instanceof self ? clone $sourceObj : new self($sourceObj);
		$t->id = isset($source['id']) ? (int) $source['id'] : null;
		$t->fullName = (string) $source['fullName'];
		$t->username = (string) $source['username'];
		$t->email = (string) $source['email'];
		$t->password = (string) $source['password'];
		$t->roles = $source['roles'] ?? null;
		return $t;
	}


	public static function fromIterable(?UserData $sourceObj, iterable $source, ?callable $mapFunction = null): self
	{
		$t = $sourceObj instanceof self ? clone $sourceObj : new self($sourceObj);
		foreach ($source as $prop => $value) {
			switch ($prop) {
				case 'id': $t->id = $mapFunction ? $mapFunction($value) : $value; break;
				case 'fullName': $t->fullName = $mapFunction ? $mapFunction($value) : $value; break;
				case 'username': $t->username = $mapFunction ? $mapFunction($value) : $value; break;
				case 'email': $t->email = $mapFunction ? $mapFunction($value) : $value; break;
				case 'password': $t->password = $mapFunction ? $mapFunction($value) : $value; break;
				case 'roles': $t->roles = $mapFunction ? $mapFunction($value) : $value; break;
				default: throw new InvalidArgumentException('Unknown property: "' . $prop . '" not in ' . __CLASS__);
			}
		}
		return $t;
	}


	public function getId(): ?int
	{
		return $this->id;
	}


	public function getFullName(): string
	{
		return $this->fullName;
	}


	public function getUsername(): string
	{
		return $this->username;
	}


	public function getEmail(): string
	{
		return $this->email;
	}


	public function getPassword(): string
	{
		return $this->password;
	}


	public function getRoles(): array
	{
		return $this->roles;
	}


	public static function get(UserData $source, string $propertyName)
	{
		switch ($propertyName) {
			case 'id': return $source->getId();
			case 'fullName': return $source->getFullName();
			case 'username': return $source->getUsername();
			case 'email': return $source->getEmail();
			case 'password': return $source->getPassword();
			case 'roles': return $source->getRoles();
			default: throw new \InvalidArgumentException("Unknown property: " . $propertyName);
		}
	}


	public function withId(?int $id): self
	{
		$t = clone $this;
		$t->id = $id;
		return $t;
	}


	public function withFullName(string $fullName): self
	{
		$t = clone $this;
		$t->fullName = $fullName;
		return $t;
	}


	public function withUsername(string $username): self
	{
		$t = clone $this;
		$t->username = $username;
		return $t;
	}


	public function withEmail(string $email): self
	{
		$t = clone $this;
		$t->email = $email;
		return $t;
	}


	public function withPassword(string $password): self
	{
		$t = clone $this;
		$t->password = $password;
		return $t;
	}


	public function withRoles(array $roles): self
	{
		$t = clone $this;
		$t->roles = $roles;
		return $t;
	}

}

