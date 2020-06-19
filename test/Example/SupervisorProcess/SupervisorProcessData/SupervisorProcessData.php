<?php declare(strict_types = 1);
//
// Generated by Smalldb\StateMachine\CodeGenerator\DtoGenerator.
// Do NOT edit! All changes will be lost!
// 
// 
namespace Smalldb\StateMachine\Test\Example\SupervisorProcess\SupervisorProcessData;

use DateTimeImmutable;
use Smalldb\StateMachine\CodeCooker\Annotation\GeneratedClass;


/**
 * @GeneratedClass
 * @see \Smalldb\StateMachine\Test\Example\SupervisorProcess\SupervisorProcessProperties
 */
interface SupervisorProcessData
{

	public function getId(): ?int;

	public function getState(): string;

	public function getCommand(): string;

	public function getCreatedAt(): DateTimeImmutable;

	public function getModifiedAt(): DateTimeImmutable;

	public function getMemoryLimit(): ?int;

	public function getArgs(): ?array;
}

