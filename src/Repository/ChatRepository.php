<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Chat;
use App\Entity\Project;
use App\Service\Chat\ChatStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Chat>
 */
class ChatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Chat::class);
    }

    public function findByProjectActiveFirst(Project $project): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.project = :project')
            ->setParameter('project', $project)
            ->addSelect('CASE WHEN c.status = :active THEN 0 ELSE 1 END AS HIDDEN status_rank')
            ->setParameter('active', ChatStatus::Open->value)
            ->addOrderBy('status_rank', 'ASC')
            ->addOrderBy('c.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
