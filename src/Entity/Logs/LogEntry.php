<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Entity\Logs;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\Logs\LogEntryRepository::class)]
#[ORM\Table(name: "log_entry")]
#[ORM\Index(name: "idx_channel", columns: ["channel"])]
#[ORM\Index(name: "idx_created_at", columns: ["created_at"])]
class LogEntry
{
    public const CHANNEL_WEBHOOK = 'webhook';
    public const CHANNEL_WORKER = 'worker';

    public const STATUS_SUCCESS = 'success';
    public const STATUS_ERROR = 'error';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 50)]
    private string $channel;

    #[ORM\Column(type: "string", length: 100)]
    private string $action;

    #[ORM\Column(type: "string", length: 20)]
    private string $status;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $message = null;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $channel,
        string $action,
        string $status,
        ?string $message = null,
    ) {
        $this->channel = $channel;
        $this->action = $action;
        $this->status = $status;
        $this->message = $message;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
