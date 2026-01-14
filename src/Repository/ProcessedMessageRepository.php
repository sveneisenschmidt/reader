<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Repository;

use App\Entity\ProcessedMessage;
use App\Enum\MessageSource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PhpStaticAnalysis\Attributes\Returns;
use PhpStaticAnalysis\Attributes\Template;

#[Template('T', ProcessedMessage::class)]
class ProcessedMessageRepository extends ServiceEntityRepository
{
    public function __construct(private ManagerRegistry $registry)
    {
        parent::__construct($registry, ProcessedMessage::class);
    }

    public function save(
        ProcessedMessage $message,
        ?int $retentionLimit = null,
    ): void {
        $em = $this->getOpenEntityManager();
        $em->persist($message);
        $em->flush();

        if ($retentionLimit !== null) {
            $this->pruneByType($message->getMessageType(), $retentionLimit);
        }
    }

    public function getLastByType(string $type): ?ProcessedMessage
    {
        return $this->findOneBy(
            ['messageType' => $type],
            ['processedAt' => 'DESC'],
        );
    }

    public function getLastSuccessByType(string $type): ?ProcessedMessage
    {
        return $this->findOneBy(
            [
                'messageType' => $type,
                'status' => ProcessedMessage::STATUS_SUCCESS,
            ],
            ['processedAt' => 'DESC'],
        );
    }

    public function getLastSuccessByTypeAndSource(
        string $type,
        MessageSource $source,
    ): ?ProcessedMessage {
        return $this->findOneBy(
            [
                'messageType' => $type,
                'status' => ProcessedMessage::STATUS_SUCCESS,
                'source' => $source->value,
            ],
            ['processedAt' => 'DESC'],
        );
    }

    #[Returns('list<ProcessedMessage>')]
    public function findByType(string $type, int $limit = 100): array
    {
        return $this->findBy(
            ['messageType' => $type],
            ['processedAt' => 'DESC'],
            $limit,
        );
    }

    public function pruneByType(string $type, int $keep): void
    {
        $idsToKeep = $this->createQueryBuilder('m')
            ->select('m.id')
            ->where('m.messageType = :type')
            ->setParameter('type', $type)
            ->orderBy('m.processedAt', 'DESC')
            ->setMaxResults($keep)
            ->getQuery()
            ->getSingleColumnResult();

        $qb = $this->getOpenEntityManager()
            ->createQueryBuilder()
            ->delete(ProcessedMessage::class, 'm')
            ->where('m.messageType = :type')
            ->setParameter('type', $type);

        if (!empty($idsToKeep)) {
            $qb->andWhere('m.id NOT IN (:ids)')->setParameter(
                'ids',
                $idsToKeep,
            );
        }

        $qb->getQuery()->execute();
    }

    #[Returns('array<string, int>')]
    public function getCountsByType(): array
    {
        $result = $this->createQueryBuilder('m')
            ->select('m.messageType, COUNT(m.id) as cnt')
            ->groupBy('m.messageType')
            ->orderBy('m.messageType', 'ASC')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['messageType']] = (int) $row['cnt'];
        }

        return $counts;
    }

    /**
     * @return array<string, array<string|null, array{count: int, lastProcessedAt: \DateTimeImmutable}>>
     */
    public function getCountsByTypeAndSource(): array
    {
        $result = $this->createQueryBuilder('m')
            ->select(
                'm.messageType, m.source, COUNT(m.id) as cnt, MAX(m.processedAt) as lastProcessedAt',
            )
            ->groupBy('m.messageType, m.source')
            ->orderBy('m.messageType', 'ASC')
            ->addOrderBy('m.source', 'ASC')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $type = $row['messageType'];
            $source = $row['source'];
            if (!isset($counts[$type])) {
                $counts[$type] = [];
            }
            $counts[$type][$source] = [
                'count' => (int) $row['cnt'],
                'lastProcessedAt' => new \DateTimeImmutable(
                    $row['lastProcessedAt'],
                ),
            ];
        }

        return $counts;
    }

    private function getOpenEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = $this->registry->getManager();

        if (!$em->isOpen()) {
            $this->registry->resetManager();
            /** @var EntityManagerInterface $em */
            $em = $this->registry->getManager();
        }

        return $em;
    }
}
