<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Domain\Feed\Entity;

use App\Domain\Feed\Repository\SubscriptionRepository;
use App\Enum\SubscriptionStatus;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
#[ORM\Table(name: 'subscription')]
#[ORM\UniqueConstraint(name: 'user_url', columns: ['user_id', 'url'])]
#[ORM\Index(name: 'idx_user_id', columns: ['user_id'])]
class Subscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    private int $userId;

    #[ORM\Column(type: 'string', length: 500)]
    private string $url;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 16)]
    private string $guid;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastRefreshedAt = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $folder = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = 'pending';

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $useArchiveIs = false;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $lastRefreshDuration = null;

    public function __construct(
        int $userId,
        string $url,
        string $name,
        string $guid,
    ) {
        $this->userId = $userId;
        $this->url = $url;
        $this->name = $name;
        $this->guid = $guid;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getGuid(): string
    {
        return $this->guid;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastRefreshedAt(): ?\DateTimeImmutable
    {
        return $this->lastRefreshedAt;
    }

    public function updateLastRefreshedAt(): self
    {
        $this->lastRefreshedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getFolder(): ?string
    {
        return $this->folder;
    }

    public function setFolder(?string $folder): self
    {
        $this->folder = $folder;

        return $this;
    }

    public function getStatus(): SubscriptionStatus
    {
        return SubscriptionStatus::from($this->status);
    }

    public function setStatus(SubscriptionStatus $status): self
    {
        $this->status = $status->value;

        return $this;
    }

    public function getUseArchiveIs(): bool
    {
        return $this->useArchiveIs;
    }

    public function setUseArchiveIs(bool $useArchiveIs): self
    {
        $this->useArchiveIs = $useArchiveIs;

        return $this;
    }

    public function getLastRefreshDuration(): ?int
    {
        return $this->lastRefreshDuration;
    }

    public function setLastRefreshDuration(?int $lastRefreshDuration): self
    {
        $this->lastRefreshDuration = $lastRefreshDuration;

        return $this;
    }
}
