<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Repository\Logs;

use App\Entity\Logs\LogEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LogEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LogEntry::class);
    }

    public function log(
        string $channel,
        string $action,
        string $status,
        ?string $message = null,
    ): LogEntry {
        $entry = new LogEntry($channel, $action, $status, $message);
        $this->getEntityManager()->persist($entry);
        $this->getEntityManager()->flush();

        return $entry;
    }

    public function getLastByChannel(string $channel): ?LogEntry
    {
        return $this->findOneBy(
            ['channel' => $channel],
            ['createdAt' => 'DESC']
        );
    }

    public function getLastByChannelAndAction(string $channel, string $action): ?LogEntry
    {
        return $this->findOneBy(
            ['channel' => $channel, 'action' => $action],
            ['createdAt' => 'DESC']
        );
    }

    public function findByChannel(string $channel, int $limit = 100): array
    {
        return $this->findBy(
            ['channel' => $channel],
            ['createdAt' => 'DESC'],
            $limit
        );
    }

    public function deleteOlderThan(\DateTimeInterface $date): int
    {
        return $this->createQueryBuilder('l')
            ->delete()
            ->where('l.createdAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }
}
