<?php

namespace Wisymfony\SlugHistoryBundle\Repository;

use Wisymfony\SlugHistoryBundle\Entity\WiSymfonySlugHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WiSymfonySlugHistory>
 *
 * @method WiSymfonySlugHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method WiSymfonySlugHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method WiSymfonySlugHistory[]    findAll()
 * @method WiSymfonySlugHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WiSymfonySlugHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WiSymfonySlugHistory::class);
    }

    public function save(WiSymfonySlugHistory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(WiSymfonySlugHistory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
