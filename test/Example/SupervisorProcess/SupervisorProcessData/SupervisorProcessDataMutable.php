<?php declare(strict_types = 1);
//
// Generated by Smalldb\CodeCooker\Generator\DtoGenerator.
// Do NOT edit! All changes will be lost!
// 
// 
namespace Smalldb\StateMachine\Test\Example\SupervisorProcess\SupervisorProcessData;

use DateTimeImmutable;
use InvalidArgumentException;
use Smalldb\CodeCooker\Annotation\GeneratedClass;
use Smalldb\StateMachine\Test\Example\SupervisorProcess\SupervisorProcessProperties as Source_SupervisorProcessProperties;


/**
 * @GeneratedClass
 * @see \Smalldb\StateMachine\Test\Example\SupervisorProcess\SupervisorProcessProperties
 */
class SupervisorProcessDataMutable extends Source_SupervisorProcessProperties implements SupervisorProcessData
{

	public function __construct(?SupervisorProcessData $source = null)
	{
		if ($source !== null) {
			if ($source instanceof Source_SupervisorProcessProperties) {
				$this->id = $source->id;
				$this->state = $source->state;
				$this->command = $source->command;
				$this->createdAt = $source->createdAt;
				$this->modifiedAt = $source->modifiedAt;
				$this->memoryLimit = $source->memoryLimit;
				$this->args = $source->args;
			} else {
				$this->id = $source->getId();
				$this->state = $source->getState();
				$this->command = $source->getCommand();
				$this->createdAt = $source->getCreatedAt();
				$this->modifiedAt = $source->getModifiedAt();
				$this->memoryLimit = $source->getMemoryLimit();
				$this->args = $source->getArgs();
			}
		}
	}


	public static function fromArray(?array $source, ?SupervisorProcessData $sourceObj = null): ?self
	{
		if ($source === null) {
			return null;
		}
		$t = $sourceObj instanceof self ? clone $sourceObj : new self($sourceObj);
		$t->id = isset($source['id']) ? (int) $source['id'] : null;
		$t->state = (string) $source['state'];
		$t->command = (string) $source['command'];
		$t->createdAt = ($v = $source['createdAt'] ?? null) instanceof DateTimeImmutable || $v === null ? $v : new DateTimeImmutable($v);
		$t->modifiedAt = ($v = $source['modifiedAt'] ?? null) instanceof DateTimeImmutable || $v === null ? $v : new DateTimeImmutable($v);
		$t->memoryLimit = isset($source['memoryLimit']) ? (int) $source['memoryLimit'] : null;
		$t->args = $source['args'] ?? null;
		return $t;
	}


	public static function fromIterable(?SupervisorProcessData $sourceObj, iterable $source, ?callable $mapFunction = null): self
	{
		$t = $sourceObj instanceof self ? clone $sourceObj : new self($sourceObj);
		foreach ($source as $prop => $value) {
			switch ($prop) {
				case 'id': $t->id = $mapFunction ? $mapFunction($value) : $value; break;
				case 'state': $t->state = $mapFunction ? $mapFunction($value) : $value; break;
				case 'command': $t->command = $mapFunction ? $mapFunction($value) : $value; break;
				case 'createdAt': $t->createdAt = $mapFunction ? $mapFunction($value) : $value; break;
				case 'modifiedAt': $t->modifiedAt = $mapFunction ? $mapFunction($value) : $value; break;
				case 'memoryLimit': $t->memoryLimit = $mapFunction ? $mapFunction($value) : $value; break;
				case 'args': $t->args = $mapFunction ? $mapFunction($value) : $value; break;
				default: throw new InvalidArgumentException('Unknown property: "' . $prop . '" not in ' . __CLASS__);
			}
		}
		return $t;
	}


	public function getId(): ?int
	{
		return $this->id;
	}


	public function getState(): string
	{
		return $this->state;
	}


	public function getCommand(): string
	{
		return $this->command;
	}


	public function getCreatedAt(): DateTimeImmutable
	{
		return $this->createdAt;
	}


	public function getModifiedAt(): DateTimeImmutable
	{
		return $this->modifiedAt;
	}


	public function getMemoryLimit(): ?int
	{
		return $this->memoryLimit;
	}


	public function getArgs(): ?array
	{
		return $this->args;
	}


	public static function get(SupervisorProcessData $source, string $propertyName)
	{
		switch ($propertyName) {
			case 'id': return $source->getId();
			case 'state': return $source->getState();
			case 'command': return $source->getCommand();
			case 'createdAt': return $source->getCreatedAt();
			case 'modifiedAt': return $source->getModifiedAt();
			case 'memoryLimit': return $source->getMemoryLimit();
			case 'args': return $source->getArgs();
			default: throw new \InvalidArgumentException("Unknown property: " . $propertyName);
		}
	}


	public function setId(?int $id): void
	{
		$this->id = $id;
	}


	public function setState(string $state): void
	{
		$this->state = $state;
	}


	public function setCommand(string $command): void
	{
		$this->command = $command;
	}


	public function setCreatedAt(DateTimeImmutable $createdAt): void
	{
		$this->createdAt = $createdAt;
	}


	public function setModifiedAt(DateTimeImmutable $modifiedAt): void
	{
		$this->modifiedAt = $modifiedAt;
	}


	public function setMemoryLimit(?int $memoryLimit): void
	{
		$this->memoryLimit = $memoryLimit;
	}


	public function setArgs(?array $args): void
	{
		$this->args = $args;
	}

}

