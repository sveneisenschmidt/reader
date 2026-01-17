<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Domain\ItemStatus\Entity;

use App\Domain\ItemStatus\Repository\BookmarkStatusRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BookmarkStatusRepository::class)]
#[ORM\Table(name: 'bookmark_status')]
#[
    ORM\UniqueConstraint(
        name: 'user_bookmarked_item',
        columns: ['user_id', 'feed_item_guid'],
    ),
]
#[ORM\Index(name: 'idx_bookmark_feed_item_guid', columns: ['feed_item_guid'])]
class BookmarkStatus
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    private int $userId;

    #[ORM\Column(type: 'string', length: 16)]
    private string $feedItemGuid;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $bookmarkedAt;

    public function __construct(int $userId, string $feedItemGuid)
    {
        $this->userId = $userId;
        $this->feedItemGuid = $feedItemGuid;
        $this->bookmarkedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getFeedItemGuid(): string
    {
        return $this->feedItemGuid;
    }

    public function getBookmarkedAt(): \DateTimeImmutable
    {
        return $this->bookmarkedAt;
    }
}
