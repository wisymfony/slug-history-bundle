<?php

declare(strict_types=1);

namespace Wisymfony\SlugHistoryBundle\Service\Storage;

use Wisymfony\SlugHistoryBundle\Entity\WiSymfonySlugHistory;
use Wisymfony\SlugHistoryBundle\Repository\WiSymfonySlugHistoryRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Database-backed implementation of slug history storage.
 *
 * This storage is intended to persist slug history records in a relational
 * database. The actual persistence logic is not implemented yet and serves as
 * a placeholder for applications that prefer storing slug mappings outside cache.
 */
class DatabaseSlugStorage implements SlugStorageInterface
{
    private EntityManagerInterface $em;
    private WiSymfonySlugHistoryRepository $repository;

    public function __construct(EntityManagerInterface $em, WiSymfonySlugHistoryRepository $repository)
    {
        $this->em = $em;
        $this->repository = $repository;
    }

    /**
     * Persist a slug redirect mapping in the database.
     *
     * This method should insert or update a slug history record with the
     * provided target metadata.
     *
     * @param string $oldPath
     * @param array  $newPathData
     */
    public function savePath(string $oldPath, array $newPathData): void
    {
        if (empty($oldPath)) {
            throw new \InvalidArgumentException("The ’oldPath’ argument must not be null or empty.");
        }
        if (empty($newPathData["path"])) {
            throw new \InvalidArgumentException("The ’path’ option is required in the newPathData argument and must not be empty.");
        }

        $history = $this->repository->findOneBy(['oldPathKey' => md5($oldPath)], ['lastUpdatedAt' => 'DESC']);
        if (!$history) {
            $history = new WiSymfonySlugHistory();
        }
        $history->setOldPath($oldPath);
        $history->setNewPath($newPathData["path"]);
        if (isset($newPathData["entityClass"]) && is_string($newPathData["entityClass"])) {
            $history->setEntityClass($newPathData["entityClass"]);
        }
        $this->em->persist($history);
        $this->em->flush();
    }

    /**
     * Find a stored slug redirect entry by path.
     *
     * @param string $path
     * @return array|null
     */
    public function findPath(string $path): ?array
    {
        $row = $this->repository->findOneBy(['oldPathKey' => md5($path)], ['lastUpdatedAt' => 'DESC']);
        $result = null;
        if ($row) {
            $result = [
                'path' => $row->getNewPath(),
                'lastUpdatedAt' => $row->getLastUpdatedAt()?->getTimestamp(),
                'entityClass' => $row->getEntityClass()
            ];
        }
        return $result;
    }

    /**
     * Remove a stored slug redirect entry from the database.
     *
     * @param string $path
     */
    public function removePath(string $path): void
    {
        $row = $this->repository->findOneBy(['oldPathKey' => md5($path)]);
        if ($row) {
            $previous = $this->repository->findOneBy(['newPath' => $path]);
            if ($previous) {
                $previous->setNewPath($row->getNewPath());
                $this->em->persist($previous);
            }
            $this->em->remove($row);
            $this->em->flush();
        }
    }
}
