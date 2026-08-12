<?php

declare(strict_types=1);

namespace Wisoft\SlugHistoryBundle\Service\Storage;

/**
 * Abstraction for slug history storage.
 *
 * Implementations must be able to save old path mappings, retrieve stored
 * redirect targets by path, and remove outdated entries.
 */
interface SlugStorageInterface
{
    /**
     * Persist a slug redirect mapping.
     *
     * @param string $oldPath
     * @param array  $newPathData
     */
    public function savePath(string $oldPath, array $newPathData): void;

    /**
     * Retrieve a stored slug redirect entry by path.
     *
     * @param string $path
     * @return array|null
     */
    public function findPath(string $path): ?array;

    /**
     * Remove a stored slug redirect entry.
     *
     * @param string $path
     */
    public function removePath(string $path): void;
}
