<?php
namespace Wisymfony\SlugHistoryBundle\Service\Storage;

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

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
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
        // Implement database storage logic here
    }

    /**
     * Find a stored slug redirect entry by path.
     *
     * @param string $path
     * @return array|null
     */
    public function findPath(string $path): ?array
    {
        // Implement database retrieval logic here
        return null;
    }

    /**
     * Remove a stored slug redirect entry from the database.
     *
     * @param string $path
     */
    public function removePath(string $path): void
    {
        // Implement database removal logic here
    }
}