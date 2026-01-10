<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Messenger\Middleware;

use App\Entity\Messages\ProcessedMessage;
use App\Enum\MessageSource;
use App\Message\RetainableMessageInterface;
use App\Message\SourceAwareMessageInterface;
use App\Repository\Messages\ProcessedMessageRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

class ProcessedMessageMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ManagerRegistry $registry,
        private ProcessedMessageRepository $repository,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        $retentionLimit =
            $message instanceof RetainableMessageInterface
                ? $message::getRetentionLimit()
                : null;
        $source =
            $message instanceof SourceAwareMessageInterface
                ? $message->getSource()
                : null;

        try {
            $result = $stack->next()->handle($envelope, $stack);
            $this->saveProcessedMessage(
                get_class($message),
                ProcessedMessage::STATUS_SUCCESS,
                null,
                $retentionLimit,
                $source,
            );

            return $result;
        } catch (\Throwable $e) {
            $this->saveProcessedMessage(
                get_class($message),
                ProcessedMessage::STATUS_FAILED,
                $e->getMessage(),
                $retentionLimit,
                $source,
            );
            throw $e;
        }
    }

    private function saveProcessedMessage(
        string $messageType,
        string $status,
        ?string $errorMessage,
        ?int $retentionLimit,
        ?MessageSource $source,
    ): void {
        $em = $this->registry->getManager('messages');

        if (!$em->isOpen()) {
            $this->registry->resetManager('messages');
        }

        $processedMessage = new ProcessedMessage(
            $messageType,
            $status,
            $errorMessage,
            $source,
        );

        $this->repository->save($processedMessage, $retentionLimit);
    }
}
