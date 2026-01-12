<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Service;

use App\Entity\Content\FeedItem;
use App\Repository\Content\FeedItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpStaticAnalysis\Attributes\Param;
use PhpStaticAnalysis\Attributes\Returns;

class FeedPersistenceService
{
    public function __construct(
        private FeedItemRepository $feedItemRepository,
        private EntityManagerInterface $contentEntityManager,
    ) {
    }

    #[Param(items: 'list<array<string, mixed>>')]
    public function persistFeedItems(array $items): void
    {
        $twoDaysAgo = new \DateTimeImmutable('-48 hours');

        foreach ($items as $itemData) {
            $existing = $this->feedItemRepository->findByGuid(
                $itemData['guid'],
            );

            $date = $itemData['date'];
            if ($date instanceof \DateTimeImmutable) {
                $publishedAt = $date;
            } elseif ($date instanceof \DateTime) {
                $publishedAt = \DateTimeImmutable::createFromMutable($date);
            } else {
                $publishedAt = new \DateTimeImmutable('now');
            }

            if ($existing === null) {
                $feedItem = new FeedItem(
                    $itemData['guid'],
                    $itemData['feedGuid'],
                    $itemData['title'],
                    $itemData['link'],
                    $itemData['source'],
                    $itemData['excerpt'],
                    $publishedAt,
                );
                $this->contentEntityManager->persist($feedItem);
            } elseif ($existing->getPublishedAt() > $twoDaysAgo) {
                $existing->setTitle($itemData['title']);
                $existing->setLink($itemData['link']);
                $existing->setSource($itemData['source']);
                $existing->setExcerpt($itemData['excerpt']);
            }
        }

        $this->contentEntityManager->flush();
    }

    #[Param(feedGuids: 'list<string>')]
    #[Returns('list<array<string, mixed>>')]
    public function getAllItems(array $feedGuids): array
    {
        $feedItems = $this->feedItemRepository->findByFeedGuids($feedGuids);

        return array_map(fn (FeedItem $item) => $item->toArray(), $feedItems);
    }

    #[Returns('array<string, mixed>|null')]
    public function getItemByGuid(string $guid): ?array
    {
        $feedItem = $this->feedItemRepository->findByGuid($guid);

        return $feedItem?->toArray();
    }

    public function getItemCountForFeed(string $feedGuid): int
    {
        return $this->feedItemRepository->getItemCountByFeedGuid($feedGuid);
    }
}
