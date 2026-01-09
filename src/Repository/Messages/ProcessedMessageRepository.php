<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Repository\Messages;

use App\Entity\Messages\ProcessedMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

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
            ["messageType" => $type],
            ["processedAt" => "DESC"],
        );
    }

    public function getLastSuccessByType(string $type): ?ProcessedMessage
    {
        return $this->findOneBy(
            [
                "messageType" => $type,
                "status" => ProcessedMessage::STATUS_SUCCESS,
            ],
            ["processedAt" => "DESC"],
        );
    }

    public function findByType(string $type, int $limit = 100): array
    {
        return $this->findBy(
            ["messageType" => $type],
            ["processedAt" => "DESC"],
            $limit,
        );
    }

    public function pruneByType(string $type, int $keep): void
    {
        $idsToKeep = $this->createQueryBuilder("m")
            ->select("m.id")
            ->where("m.messageType = :type")
            ->setParameter("type", $type)
            ->orderBy("m.processedAt", "DESC")
            ->setMaxResults($keep)
            ->getQuery()
            ->getSingleColumnResult();

        $qb = $this->getOpenEntityManager()
            ->createQueryBuilder()
            ->delete(ProcessedMessage::class, "m")
            ->where("m.messageType = :type")
            ->setParameter("type", $type);

        if (!empty($idsToKeep)) {
            $qb->andWhere("m.id NOT IN (:ids)")->setParameter(
                "ids",
                $idsToKeep,
            );
        }

        $qb->getQuery()->execute();
    }

    /**
     * @return array<string, int>
     */
    public function getCountsByType(): array
    {
        $result = $this->createQueryBuilder("m")
            ->select("m.messageType, COUNT(m.id) as cnt")
            ->groupBy("m.messageType")
            ->orderBy("m.messageType", "ASC")
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row["messageType"]] = (int) $row["cnt"];
        }

        return $counts;
    }

    private function getOpenEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = $this->registry->getManager("messages");

        if (!$em->isOpen()) {
            $this->registry->resetManager("messages");
            /** @var EntityManagerInterface $em */
            $em = $this->registry->getManager("messages");
        }

        return $em;
    }
}
