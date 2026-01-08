<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Controller;

use App\Entity\Logs\LogEntry;
use App\Message\CleanupContentMessage;
use App\Message\RefreshFeedsMessage;
use App\MessageHandler\CleanupContentHandler;
use App\MessageHandler\RefreshFeedsHandler;
use App\Repository\Logs\LogEntryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route("/webhook")]
class WebhookController extends AbstractController
{
    public function __construct(
        private LogEntryRepository $logEntryRepository,
    ) {}

    #[Route("/refresh-feeds", name: "webhook_refresh_feeds", methods: ["GET"])]
    public function refreshFeeds(RefreshFeedsHandler $handler): JsonResponse
    {
        try {
            $handler(new RefreshFeedsMessage());
            $this->logEntryRepository->log(
                LogEntry::CHANNEL_WEBHOOK,
                "refresh-feeds",
                LogEntry::STATUS_SUCCESS,
            );

            return new JsonResponse(["status" => "ok"]);
        } catch (\Throwable $e) {
            $this->logEntryRepository->log(
                LogEntry::CHANNEL_WEBHOOK,
                "refresh-feeds",
                LogEntry::STATUS_ERROR,
                $e->getMessage(),
            );

            return new JsonResponse(
                ["status" => "error", "message" => $e->getMessage()],
                500,
            );
        }
    }

    #[
        Route(
            "/cleanup-content",
            name: "webhook_cleanup_content",
            methods: ["GET"],
        ),
    ]
    public function cleanupContent(CleanupContentHandler $handler): JsonResponse
    {
        try {
            $handler(new CleanupContentMessage(olderThanDays: 30));
            $this->logEntryRepository->log(
                LogEntry::CHANNEL_WEBHOOK,
                "cleanup-content",
                LogEntry::STATUS_SUCCESS,
            );

            return new JsonResponse(["status" => "ok"]);
        } catch (\Throwable $e) {
            $this->logEntryRepository->log(
                LogEntry::CHANNEL_WEBHOOK,
                "cleanup-content",
                LogEntry::STATUS_ERROR,
                $e->getMessage(),
            );

            return new JsonResponse(
                ["status" => "error", "message" => $e->getMessage()],
                500,
            );
        }
    }
}
