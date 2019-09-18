<?php declare(strict_types = 1);
/*
 * Copyright (c) 2017-2019, Josef Kufner  <josef@kufner.cz>
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

namespace Smalldb\StateMachine\Utils;


/**
 * Write PHP files in a convenient way.
 */
class PhpFileWriter
{
	private $indent = "";
	private $indentDepth = 0;

	private $buffer = '';

	private $headerComment = null;
	private $fileNamespace = null;
	private $generatorName = null;

	/** @var string[] */
	private $useAliases = [];
	/** @var int[] */
	private $usedAliasesCounter = [];

	/** @var int[] */
	private $usedIdentifiersCounter = [];

	/** @var string[] */
	private $definedMethodNames = [];

	/**
	 * PhpFileWriter constructor.
	 */
	public function __construct()
	{
	}


	public function write(string $filename)
	{
		$this->eof();
		file_put_contents($filename, $this->getPhpCode());
	}

	public function getPhpCode(): string
	{
		$this->eof();

		$code = "<?php declare(strict_types = 1);\n";
		$code .= $this->headerComment;
		$code .= "\n";

		if ($this->fileNamespace) {
			$code .= "namespace " . $this->fileNamespace . ";\n\n";
		}
		ksort($this->useAliases);
		foreach ($this->useAliases as $className => $alias) {
			if ($this->getShortClassName($className) === $alias) {
				$code .= "use $className;\n";
			} else {
				$code .= "use $className as $alias;\n";
			}
		}

		$code .= "\n";
		$code .= $this->buffer;
		$code .= "\n";
		return $code;
	}


	/**
	 * @throws \ReflectionException
	 */
	public function getParamAsCode(\ReflectionParameter $param) : string
	{
		$code = '$' . $param->name;

		if ($param->isPassedByReference()) {
			$code = '& ' . $code;
		}

		if ($param->isVariadic()) {
			$code = '... ' . $code;
		}

		if (($type = $param->getType()) !== null) {
			$type = (string) $type;
			if (class_exists($type)) {
				$type = $this->useClass($type);
			}
			$code = $type . ' ' . $code;

			if ($param->allowsNull()) {
				$code = '?' . $code;
			}
		}

		if ($param->isDefaultValueAvailable()) {
			if ($param->isDefaultValueConstant()) {
				$code .= ' = ' . $param->getDefaultValueConstantName();
			} else {
				$code .= ' = ' . var_export($param->getDefaultValue(), true);
			}
		}

		return $code;
	}


	public static function toCamelCase(string $identifier): string
	{
		return str_replace('_', '', ucwords($identifier, '_'));
	}


	public static function getShortClassName(string $fqcn): string
	{
		$lastSlashPos = strrpos($fqcn, '\\');
		return $lastSlashPos === false ? $fqcn : substr($fqcn, $lastSlashPos + 1);
	}


	public static function getClassNamespace(string $fqcn): string
	{
		$lastSlashPos = strrpos($fqcn, '\\');
		return $lastSlashPos === false ? '' : substr($fqcn, $fqcn[0] == '/' ? 1 : 0, $lastSlashPos);
	}


	public function useClass(string $fqcn): string
	{
		if (isset($this->useAliases[$fqcn])) {
			return $this->useAliases[$fqcn];
		} else {
			$alias = $this->getShortClassName($fqcn);
			if (isset($this->usedAliasesCounter[$alias])) {
				$alias .= '_' . ($this->usedAliasesCounter[$alias]++);
			} else if (preg_match('/_[0-9]+$/', $alias)) {
				$this->usedAliasesCounter[$alias] = 1;
				$alias .= '_0';
			} else {
				$this->usedAliasesCounter[$alias] = 1;
			}
			$this->useAliases[$fqcn] = $alias;
			return $alias;
		}
	}


	public function getIdentifier(string $name, string $suffix = null): string
	{
		$identifier = (string) preg_replace('/[^a-zA-Z0-9]/', '_', $name . ($suffix === null ? '' : '_' . $suffix));

		if (isset($this->usedIdentifiersCounter[$identifier])) {
			$identifier .= '_' . ($this->usedIdentifiersCounter[$identifier]++);
		} else if (preg_match('/_[0-9]+$/', $identifier)) {
			$this->usedIdentifiersCounter[$identifier] = 1;
			$identifier .= '_0';
		} else {
			$this->usedIdentifiersCounter[$identifier] = 1;
		}
		return $identifier;
	}


	private function decreaseIndent(): void
	{
		if ($this->indentDepth <= 0) {
			throw new \LogicException("Indentation level reached zero. No block left to end when generating a PHP file.");
		}
		$this->indentDepth--;
		$this->indent = str_repeat("\t", $this->indentDepth);
	}


	private function increaseIndent(): void
	{
		$this->indent = $this->indent . "\t";
		$this->indentDepth++;
	}


	private $lineIndented = false;

	public function writeString(string $string = '', ...$args): self
	{
		if ($string !== '') {
			if (!$this->lineIndented) {
				$this->buffer .= $this->indent;
				$this->lineIndented = true;
			}
			if (count($args) > 0) {
				$this->buffer .= vsprintf($string, array_map(function($v) { return var_export($v, true); }, $args));
			} else {
				$this->buffer .= $string;
			}
		}
		return $this;
	}

	public function writeln(string $string = '', ...$args): self
	{
		if ($string !== '') {
			if (!$this->lineIndented) {
				$this->buffer .= $this->indent;
			}
			if (count($args) > 0) {
				$this->buffer .= vsprintf($string, array_map(function($v) { return var_export($v, true); }, $args));
			} else {
				$this->buffer .= $string;
			}
		}
		$this->buffer .= "\n";
		$this->lineIndented = false;
		return $this;
	}


	public function eof(): self
	{
		if ($this->indentDepth !== 0) {
			throw new \LogicException("Block not closed when generating a PHP file.");
		}
		return $this;
	}


	public function setFileHeader(string $generator_name): self
	{
		$this->headerComment = "//" . str_replace("\n", "\n// ", "\n"
			. "Generated by $generator_name.\n"
			. ""
			. "Do NOT edit! All changes will be lost!\n"
			. "\n");
		return $this;
	}


	public function beginBlock(string $statement = '', ...$args): self
	{
		if ($statement === '') {
			$this->writeln("{");
		} else {
			$this->writeln("$statement {", ...$args);
		}
		$this->increaseIndent();
		return $this;
	}


	public function midBlock(string $statement, ...$args): self
	{
		$this->decreaseIndent();
		$this->writeln("} $statement {", ...$args);
		$this->increaseIndent();
		return $this;
	}


	public function endBlock(string $suffix = '', ...$args): self
	{
		$this->decreaseIndent();
		if ($suffix === '') {
			$this->writeln("}");
		} else {
			$this->writeln("}$suffix", ...$args);
		}
		return $this;
	}


	public function comment(string $comment): self
	{
		$this->writeln("// ".str_replace("\n", "\n// ", $comment));
		return $this;
	}


	public function setClassName(string $className): self
	{
		if (isset($this->useAliases[$className])) {
			throw new \LogicException('Class name already used as an alias: ' . $className);
		}

		$this->usedAliasesCounter[$className] = 1;

		return $this;
	}


	public function setNamespace(string $namespace): self
	{
		$this->fileNamespace = $namespace;
		return $this;
	}


	public function beginClass(string $classname, ?string $extends = null, array $implements = []): self
	{
		$this->writeln("class $classname"
			. ($extends ? " extends " . $extends : '')
			. ($implements  ? " implements " . join(', ', $implements) : ''));
		$this->beginBlock();
		return $this;
	}


	public function beginAbstractClass(string $classname): self
	{
		$this->writeln("abstract class $classname");
		$this->beginBlock();
		return $this;
	}


	public function endClass(): self
	{
		$this->endBlock();
		return $this;
	}


	public function beginMethod(string $name, array $args = [], string $returnType = ''): self
	{
		$this->definedMethodNames[$name] = $name;
		$this->writeln('');
		$this->writeln("public function $name(".join(', ', $args).")".($returnType === '' ? '' : ": $returnType"));
		$this->beginBlock();
		return $this;
	}

	public function beginProtectedMethod(string $name, array $args = [], string $returnType = ''): self
	{
		$this->definedMethodNames[$name] = $name;
		$this->writeln('');
		$this->writeln("protected function $name(".join(', ', $args).")".($returnType === '' ? '' : ": $returnType"));
		$this->beginBlock();
		return $this;
	}

	public function beginPrivateMethod(string $name, array $args = [], string $returnType = ''): self
	{
		$this->definedMethodNames[$name] = $name;
		$this->writeln('');
		$this->writeln("private function $name(".join(', ', $args).")".($returnType === '' ? '' : ": $returnType"));
		$this->beginBlock();
		return $this;
	}

	public function beginFinalMethod(string $name, array $args = [], string $returnType = ''): self
	{
		$this->definedMethodNames[$name] = $name;
		$this->writeln('');
		$this->writeln("public final function $name(".join(', ', $args).")".($returnType === '' ? '' : ": $returnType"));
		$this->beginBlock();
		return $this;
	}

	public function beginStaticMethod(string $name, array $args = [], string $returnType = ''): self
	{
		$this->definedMethodNames[$name] = $name;
		$this->writeln('');
		$this->writeln("public static function $name(".join(', ', $args).")".($returnType === '' ? '' : ": $returnType"));
		$this->beginBlock();
		return $this;
	}


	public function endMethod(): self
	{
		$this->endBlock();
		$this->writeln('');
		return $this;
	}


	public function hasMethod(string $methodName)
	{
		return isset($this->definedMethodNames[$methodName]);
	}

}
