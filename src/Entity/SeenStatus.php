<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Entity;

use App\Repository\SeenStatusRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SeenStatusRepository::class)]
#[ORM\Table(name: 'seen_status')]
#[
    ORM\UniqueConstraint(
        name: 'user_feed_item_seen',
        columns: ['user_id', 'feed_item_guid'],
    ),
]
class SeenStatus
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
    private \DateTimeImmutable $seenAt;

    public function __construct(int $userId, string $feedItemGuid)
    {
        $this->userId = $userId;
        $this->feedItemGuid = $feedItemGuid;
        $this->seenAt = new \DateTimeImmutable();
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

    public function getSeenAt(): \DateTimeImmutable
    {
        return $this->seenAt;
    }
}
