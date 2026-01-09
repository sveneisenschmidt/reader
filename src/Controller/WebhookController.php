<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Controller;

use App\Message\CleanupContentMessage;
use App\Message\RefreshFeedsMessage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route("/webhook")]
class WebhookController extends AbstractController
{
    #[Route("/refresh-feeds", name: "webhook_refresh_feeds", methods: ["GET"])]
    public function refreshFeeds(MessageBusInterface $bus): JsonResponse
    {
        try {
            $bus->dispatch(new RefreshFeedsMessage());

            return new JsonResponse(["status" => "success"]);
        } catch (\Throwable $e) {
            return new JsonResponse(
                [
                    "status" => "error",
                    "message" => $e->getMessage(),
                ],
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
    public function cleanupContent(MessageBusInterface $bus): JsonResponse
    {
        try {
            $bus->dispatch(new CleanupContentMessage(olderThanDays: 30));

            return new JsonResponse(["status" => "success"]);
        } catch (\Throwable $e) {
            return new JsonResponse(
                [
                    "status" => "error",
                    "message" => $e->getMessage(),
                ],
                500,
            );
        }
    }
}
