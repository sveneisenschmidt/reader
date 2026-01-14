<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Entity;

use App\Entity\ProcessedMessage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ProcessedMessageTest extends TestCase
{
    #[Test]
    public function constructorSetsAllProperties(): void
    {
        $message = new ProcessedMessage(
            'App\\Message\\TestMessage',
            ProcessedMessage::STATUS_SUCCESS,
        );

        $this->assertEquals('App\\Message\\TestMessage', $message->getMessageType());
        $this->assertEquals(ProcessedMessage::STATUS_SUCCESS, $message->getStatus());
        $this->assertNull($message->getErrorMessage());
    }

    #[Test]
    public function constructorSetsErrorMessage(): void
    {
        $message = new ProcessedMessage(
            'App\\Message\\TestMessage',
            ProcessedMessage::STATUS_FAILED,
            'Something went wrong',
        );

        $this->assertEquals(ProcessedMessage::STATUS_FAILED, $message->getStatus());
        $this->assertEquals('Something went wrong', $message->getErrorMessage());
    }

    #[Test]
    public function idIsNullBeforePersist(): void
    {
        $message = new ProcessedMessage(
            'App\\Message\\TestMessage',
            ProcessedMessage::STATUS_SUCCESS,
        );

        $this->assertNull($message->getId());
    }

    #[Test]
    public function processedAtIsSetOnConstruction(): void
    {
        $before = new \DateTimeImmutable();
        $message = new ProcessedMessage(
            'App\\Message\\TestMessage',
            ProcessedMessage::STATUS_SUCCESS,
        );
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $message->getProcessedAt());
        $this->assertLessThanOrEqual($after, $message->getProcessedAt());
    }

    #[Test]
    public function statusConstantsAreDefined(): void
    {
        $this->assertEquals('success', ProcessedMessage::STATUS_SUCCESS);
        $this->assertEquals('failed', ProcessedMessage::STATUS_FAILED);
    }
}
