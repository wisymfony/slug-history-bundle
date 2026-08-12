<?php

namespace Wisoft\SlugHistoryBundle\Repository;

use Wisoft\SlugHistoryBundle\Entity\WsSlugHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WsSlugHistory>
 *
 * @method WsSlugHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method WsSlugHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method WsSlugHistory[]    findAll()
 * @method WsSlugHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WsSlugHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WsSlugHistory::class);
    }

    public function save(WsSlugHistory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(WsSlugHistory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
