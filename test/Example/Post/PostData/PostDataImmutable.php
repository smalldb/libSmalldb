<?php declare(strict_types = 1);
//
// Generated by Smalldb\CodeCooker\Generator\DtoGenerator.
// Do NOT edit! All changes will be lost!
// 
// 
namespace Smalldb\StateMachine\Test\Example\Post\PostData;

use DateTimeImmutable;
use InvalidArgumentException;
use Smalldb\CodeCooker\Annotation\GeneratedClass;
use Smalldb\StateMachine\Test\Example\Post\PostProperties as Source_PostProperties;


/**
 * @GeneratedClass
 * @see \Smalldb\StateMachine\Test\Example\Post\PostProperties
 */
class PostDataImmutable extends Source_PostProperties implements PostData
{

	public function __construct(?PostData $source = null)
	{
		if ($source !== null) {
			if ($source instanceof Source_PostProperties) {
				$this->id = $source->id;
				$this->title = $source->title;
				$this->slug = $source->slug;
				$this->summary = $source->summary;
				$this->content = $source->content;
				$this->publishedAt = $source->publishedAt;
				$this->authorId = $source->authorId;
				$this->commentCount = $source->commentCount;
			} else {
				$this->id = $source->getId();
				$this->title = $source->getTitle();
				$this->slug = $source->getSlug();
				$this->summary = $source->getSummary();
				$this->content = $source->getContent();
				$this->publishedAt = $source->getPublishedAt();
				$this->authorId = $source->getAuthorId();
				$this->commentCount = $source->getCommentCount();
			}
		}
	}


	public static function fromArray(?array $source, ?PostData $sourceObj = null): ?self
	{
		if ($source === null) {
			return null;
		}
		$t = $sourceObj instanceof self ? clone $sourceObj : new self($sourceObj);
		$t->id = isset($source['id']) ? (int) $source['id'] : null;
		$t->title = (string) $source['title'];
		$t->slug = (string) $source['slug'];
		$t->summary = (string) $source['summary'];
		$t->content = (string) $source['content'];
		$t->publishedAt = ($v = $source['publishedAt'] ?? null) instanceof \DateTimeImmutable || $v === null ? $v : ($v instanceof \DateTime ? \DateTimeImmutable::createFromMutable($v) : new \DateTimeImmutable($v));
		$t->authorId = (int) $source['authorId'];
		$t->commentCount = isset($source['commentCount']) ? (int) $source['commentCount'] : null;
		return $t;
	}


	public static function fromIterable(?PostData $sourceObj, iterable $source): self
	{
		$t = $sourceObj instanceof self ? clone $sourceObj : new self($sourceObj);
		foreach ($source as $prop => $value) {
			switch ($prop) {
				case 'id': $t->id = $value; break;
				case 'title': $t->title = $value; break;
				case 'slug': $t->slug = $value; break;
				case 'summary': $t->summary = $value; break;
				case 'content': $t->content = $value; break;
				case 'publishedAt': $t->publishedAt = $value instanceof \DateTime ? \DateTimeImmutable::createFromMutable($value) : $value; break;
				case 'authorId': $t->authorId = $value; break;
				case 'commentCount': $t->commentCount = $value; break;
				default: throw new InvalidArgumentException('Unknown property: "' . $prop . '" not in ' . __CLASS__);
			}
		}
		return $t;
	}


	public function getId(): ?int
	{
		return $this->id;
	}


	public function getTitle(): string
	{
		return $this->title;
	}


	public function getSlug(): string
	{
		return $this->slug;
	}


	public function getSummary(): string
	{
		return $this->summary;
	}


	public function getContent(): string
	{
		return $this->content;
	}


	public function getPublishedAt(): DateTimeImmutable
	{
		return $this->publishedAt;
	}


	public function getAuthorId(): int
	{
		return $this->authorId;
	}


	public function getCommentCount(): ?int
	{
		return $this->commentCount;
	}


	public static function get(PostData $source, string $propertyName)
	{
		switch ($propertyName) {
			case 'id': return $source->getId();
			case 'title': return $source->getTitle();
			case 'slug': return $source->getSlug();
			case 'summary': return $source->getSummary();
			case 'content': return $source->getContent();
			case 'publishedAt': return $source->getPublishedAt();
			case 'authorId': return $source->getAuthorId();
			case 'commentCount': return $source->getCommentCount();
			default: throw new \InvalidArgumentException("Unknown property: " . $propertyName);
		}
	}


	public function withId(?int $id): self
	{
		$t = clone $this;
		$t->id = $id;
		return $t;
	}


	public function withTitle(string $title): self
	{
		$t = clone $this;
		$t->title = $title;
		return $t;
	}


	public function withSlug(string $slug): self
	{
		$t = clone $this;
		$t->slug = $slug;
		return $t;
	}


	public function withSummary(string $summary): self
	{
		$t = clone $this;
		$t->summary = $summary;
		return $t;
	}


	public function withContent(string $content): self
	{
		$t = clone $this;
		$t->content = $content;
		return $t;
	}


	public function withPublishedAt(DateTimeImmutable $publishedAt): self
	{
		$t = clone $this;
		$t->publishedAt = $publishedAt;
		return $t;
	}


	public function withAuthorId(int $authorId): self
	{
		$t = clone $this;
		$t->authorId = $authorId;
		return $t;
	}


	public function withCommentCount(?int $commentCount): self
	{
		$t = clone $this;
		$t->commentCount = $commentCount;
		return $t;
	}

}

