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

namespace Smalldb\StateMachine\CodeGenerator\InferClass;


class TypeResolver
{

	/** @var \ReflectionClass */
	private $class;

	/** @var string */
	private $namespace;

	/** @var array */
	private $aliases = [];

	/** @var array */
	private $tokens;

	/** @var int */
	private $curToken;

	/** @var string */
	private $curTokenStr;

	/** @var int */
	private $curLine = 1;


	public function __construct(\ReflectionClass $class)
	{
		$this->class = $class;
		$this->namespace = $class->getNamespaceName();
		$this->parse($class->getFileName());
	}


	public function resolveClassName($className): string
	{
		// Don't resolve primitive types
		switch ($className) {
			case 'self':
				return $this->class->getName();
			case 'static':
			case 'array':
			case 'callable':
			case 'bool':
			case 'float':
			case 'int':
			case 'string':
			case 'iterable':
			case 'object':
				return $className;
		}

		$slashPos = strpos($className, '\\');

		// FQCN
		if ($slashPos === 0) {
			return substr($className, 1);
		}

		// Class name without any slash
		if ($slashPos === false) {
			return $this->aliases[$className] ?? $this->namespace . '\\' . $className;
		}

		[ $head, $rest ] = explode($className, '\\', 2);

		if (isset($this->aliases[$head])) {
			// Alliased namespace
			return $this->aliases[$head] . '\\' . $rest;
		} else {
			// Current namespace
			return $this->namespace . '\\' . $className;
		}

	}


	private function parse(string $filename)
	{
		$this->tokens = token_get_all(file_get_contents($filename));
		$this->nextToken(true);

		$this->parseExpectToken(T_OPEN_TAG);

		while ($this->curToken) {
			switch ($this->curToken) {
				case T_USE:
					$this->parseUseStatement();
					break;
				case '{':
				case T_CLASS:
				case T_FUNCTION:
				case T_INTERFACE:
				case T_TRAIT:
					// Stop at the begining of the class.
					return;
				default:
					$this->nextToken();
					break;
			}
		}
	}


	private function addAlias(string $classname, string $alias = null)
	{
		if ($alias === null) {
			$alias = $this->basename($classname);
		}
		$this->aliases[$alias] = $classname;
	}


	private function parseUseStatement()
	{
		$this->parseExpectToken(T_USE);
		$this->parseExpectWhitespace();

		if ($this->curToken === T_CONST || $this->curToken === T_FUNCTION) {
			while ($this->curToken !== ';') {
				$this->nextToken();
			}
			$this->parseExpectToken(';');
			return;
		}

		$className = $this->parseClassName();

		if ($this->curToken === ';') {
			$this->nextToken();
			$this->addAlias($className);
			return;
		}

		$this->parseExpectWhitespace();
		$this->parseExpectToken(T_AS);
		$this->parseExpectWhitespace();

		$alias = $this->parseExpectToken(T_STRING);

		$this->parseSkipWhitespace();
		$this->parseExpectToken(';');

		$this->addAlias($className, $alias);

		// TODO: Support grouped use declarations.
	}


	private function parseClassName(): string
	{
		$className = '';

		while ($this->curToken === T_STRING || $this->curToken === T_NS_SEPARATOR) {
			$className .= $this->curTokenStr;
			$this->nextToken();
		}

		return $className;
	}


	private function parseSkipCurlyBlock()
	{
		$this->parseExpectToken('{');
		while ($this->curToken !== '}') {
			if ($this->curToken === '{') {
				$this->parseSkipCurlyBlock();
			} else {
				$this->nextToken();
			}
		}
		$this->parseExpectToken('}');
	}


	private function parseExpectToken($token): string
	{
		if ($this->curToken === $token) {
			$str = $this->curTokenStr;
			$this->nextToken();
			return $str;
		} else {
			throw new \RuntimeException('Unexpected token '
				. (is_int($this->curToken) ? token_name($this->curToken) : var_export($this->curToken, true))
				. ' at line ' . $this->curLine . '. Expecting '
				. (is_int($token) ? token_name($token) : var_export($token, true))
				. '.');
		}
	}


	private function parseExpectWhitespace()
	{
		if ($this->curToken === T_WHITESPACE) {
			while ($this->curToken === T_WHITESPACE) {
				$this->nextToken();
			}
		} else {
			throw new \RuntimeException('Unexpected token '
				. (is_int($this->curToken) ? token_name($this->curToken) : var_export($this->curToken, true))
				. ' at line ' . $this->curLine . '. Expecting ' . token_name(T_WHITESPACE) . '.');
		}
	}

	private function parseSkipWhitespace()
	{
		while ($this->curToken === T_WHITESPACE) {
			$this->nextToken();
		}
	}


	private function nextToken($reset = false)
	{
		if ($reset) {
			$t = reset($this->tokens);
		} else {
			$t = next($this->tokens);
		}
		if ($this->curToken === false) {
			throw new \RuntimeException("Unexpected end of file.");
		}
		if (is_string($t)) {
			$this->curToken = $t;
			$this->curTokenStr = $t;
		} else {
			[$this->curToken, $this->curTokenStr, $this->curLine] = $t;
		}
		return $this->curToken;
	}


	/**
	 * Like basename() function but for PHP namespaces.
	 */
	private function basename(string $class): string
	{
		$p = strrpos($class, '\\');
		return $p === false ? $class : substr($class, $p + 1);
	}

}
