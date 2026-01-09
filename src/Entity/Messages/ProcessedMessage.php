<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Entity\Messages;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\Messages\ProcessedMessageRepository::class)]
#[ORM\Table(name: "processed_messages")]
#[ORM\Index(name: "idx_message_type", columns: ["message_type"])]
#[ORM\Index(name: "idx_processed_at", columns: ["processed_at"])]
class ProcessedMessage
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 255)]
    private string $messageType;

    #[ORM\Column(type: "string", length: 20)]
    private string $status;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $processedAt;

    public function __construct(
        string $messageType,
        string $status,
        ?string $errorMessage = null,
    ) {
        $this->messageType = $messageType;
        $this->status = $status;
        $this->errorMessage = $errorMessage;
        $this->processedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMessageType(): string
    {
        return $this->messageType;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getProcessedAt(): \DateTimeImmutable
    {
        return $this->processedAt;
    }
}
