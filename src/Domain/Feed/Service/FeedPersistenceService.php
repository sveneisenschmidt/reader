<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Domain\Feed\Service;

use App\Domain\Feed\Entity\FeedItem;
use App\Domain\Feed\Repository\FeedItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpStaticAnalysis\Attributes\Param;
use PhpStaticAnalysis\Attributes\Returns;

class FeedPersistenceService
{
    public function __construct(
        private FeedItemRepository $feedItemRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Param(items: 'list<array<string, mixed>>')]
    public function persistFeedItems(array $items): void
    {
        $items = $this->deduplicateByGuid($items);
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
                    $itemData['subscriptionGuid'],
                    $itemData['title'],
                    $itemData['link'],
                    $itemData['source'],
                    $itemData['excerpt'],
                    $publishedAt,
                );
                $this->entityManager->persist($feedItem);
            } elseif ($existing->getPublishedAt() > $twoDaysAgo) {
                $existing->setTitle($itemData['title']);
                $existing->setLink($itemData['link']);
                $existing->setSource($itemData['source']);
                $existing->setExcerpt($itemData['excerpt']);
            }
        }

        $this->entityManager->flush();
    }

    public function getItemCountForSubscription(string $subscriptionGuid): int
    {
        return $this->feedItemRepository->getItemCountBySubscriptionGuid(
            $subscriptionGuid,
        );
    }

    /**
     * Deletes duplicate feed items based on title similarity.
     *
     * @return int Number of deleted duplicates
     */
    public function deleteDuplicates(): int
    {
        return $this->feedItemRepository->deleteDuplicates();
    }

    #[Param(items: 'list<array<string, mixed>>')]
    #[Returns('list<array<string, mixed>>')]
    private function deduplicateByGuid(array $items): array
    {
        $guids = array_column($items, 'guid');

        return array_values(array_combine($guids, $items));
    }
}
