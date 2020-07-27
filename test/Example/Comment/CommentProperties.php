<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Smalldb\StateMachine\Test\Example\Comment;

use function Symfony\Component\String\u;
use Smalldb\CodeCooker\Annotation\GenerateDTO;
use Smalldb\StateMachine\SqlExtension\Annotation\SQL;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * @GenerateDTO("CommentData")
 * @SQL\Table("symfony_demo_comment")
 * @SQL\StateSelect("'Exists'")
 *
 * Defines the properties of the Comment entity to represent the blog comments.
 * See https://symfony.com/doc/current/doctrine.html#creating-an-entity-class
 *
 * Tip: if you have an existing database, you can generate these entity class automatically.
 * See https://symfony.com/doc/current/doctrine/reverse_engineering.html
 *
 * @author Ryan Weaver <weaverryan@gmail.com>
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 * @author Josef Kufner <josef@kufner.cz>
 */
abstract class CommentProperties
{
	/**
	 * @SQL\Id
	 * @SQL\Column(type="integer")
	 */
	protected ?int $id = null;

	/**
	 * @SQL\Column("post_id")
	 */
	protected int $postId;

	/**
	 * @SQL\Column(type="text")
	 * @Assert\NotBlank(message="comment.blank")
	 * @Assert\Length(
	 *     min=5,
	 *     minMessage="comment.too_short",
	 *     max=10000,
	 *     maxMessage="comment.too_long"
	 * )
	 */
	protected string $content = '';

	/**
	 * @SQL\Column(name="published_at", type="datetime")
	 */
	protected \DateTimeImmutable $publishedAt;

	/**
	 * @SQL\Column(name="author_id")
	 */
	protected int $authorId;


	/**
	 * @Assert\IsTrue(message="comment.is_spam")
	 */
	public function isLegitComment(): bool
	{
		$containsInvalidCharacters = null !== u($this->content)->indexOf('@');

		return !$containsInvalidCharacters;
	}

}
