<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Entity\Content;

use Doctrine\ORM\Mapping as ORM;
use PhpStaticAnalysis\Attributes\Returns;

#[
    ORM\Entity(
        repositoryClass: \App\Repository\Content\FeedItemRepository::class,
    ),
]
#[ORM\Table(name: 'feed_item')]
#[ORM\Index(name: 'idx_subscription_guid', columns: ['subscription_guid'])]
#[ORM\Index(name: 'idx_published_at', columns: ['published_at'])]
class FeedItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 16, unique: true)]
    private string $guid;

    #[ORM\Column(name: 'subscription_guid', type: 'string', length: 16)]
    private string $subscriptionGuid;

    #[ORM\Column(type: 'string', length: 500)]
    private string $title;

    #[ORM\Column(type: 'string', length: 1000)]
    private string $link;

    #[ORM\Column(type: 'string', length: 255)]
    private string $source;

    #[ORM\Column(type: 'text')]
    private string $excerpt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $publishedAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $fetchedAt;

    public function __construct(
        string $guid,
        string $subscriptionGuid,
        string $title,
        string $link,
        string $source,
        string $excerpt,
        \DateTimeImmutable $publishedAt,
    ) {
        $this->guid = $guid;
        $this->subscriptionGuid = $subscriptionGuid;
        $this->title = $title;
        $this->link = $link;
        $this->source = $source;
        $this->excerpt = $excerpt;
        $this->publishedAt = $publishedAt;
        $this->fetchedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGuid(): string
    {
        return $this->guid;
    }

    public function getSubscriptionGuid(): string
    {
        return $this->subscriptionGuid;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getLink(): string
    {
        return $this->link;
    }

    public function setLink(string $link): self
    {
        $this->link = $link;

        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getExcerpt(): string
    {
        return $this->excerpt;
    }

    public function setExcerpt(string $excerpt): self
    {
        $this->excerpt = $excerpt;

        return $this;
    }

    public function getPublishedAt(): \DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function getFetchedAt(): \DateTimeImmutable
    {
        return $this->fetchedAt;
    }

    public function updateFetchedAt(): self
    {
        $this->fetchedAt = new \DateTimeImmutable();

        return $this;
    }

    #[Returns('array<string, mixed>')]
    public function toArray(): array
    {
        return [
            'guid' => $this->guid,
            'sguid' => $this->subscriptionGuid,
            'title' => $this->title,
            'link' => $this->link,
            'source' => $this->source,
            'excerpt' => $this->excerpt,
            'date' => $this->publishedAt,
        ];
    }
}
