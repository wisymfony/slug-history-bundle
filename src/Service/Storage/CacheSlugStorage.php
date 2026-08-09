<?php

namespace Wisymfony\SlugHistoryBundle\Service\Storage;

use Symfony\Contracts\Cache\CacheInterface;

/**
 * Cache-backed implementation of slug history storage.
 *
 * This storage implementation persists old slug paths and their current
 * target data in a Symfony cache pool. It is used by `SlugManager` to
 * save, resolve and remove slug redirect entries without requiring a database.
 */
final class CacheSlugStorage implements SlugStorageInterface
{
    private CacheInterface $cache;

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Persist a slug redirect mapping in cache.
     *
     * The cached entry contains the new target path and metadata used by the
     * redirect resolver.
     *
     * @param string $oldPath
     * @param array  $newPathData
     */
    public function savePath(string $oldPath, array $newPathData): void
    {
        $cacheKey = $this->generateCacheKeyBy($oldPath);
        $this->cache->delete($cacheKey);
        $this->cache->get($cacheKey, fn () => $newPathData);
    }

    /**
     * Retrieve a stored slug redirect entry by path.
     *
     * @param string $path
     * @return array|null
     */
    public function findPath(string $path): ?array
    {
        return $this->cache->get($this->generateCacheKeyBy($path), function () {
            return null;
        });
    }

    /**
     * Remove a slug redirect entry from cache.
     *
     * If the removed entry has a predecessor reference, the predecessor is
     * rewired to point to the successor path to preserve redirect chains.
     *
     * @param string $path
     */
    public function removePath(string $path): void
    {
        $pathData = $this->readCacheEntry($path);
        if (is_array($pathData) && isset($pathData['path'])) {
            $pathFinal = $pathData['path'];
            if (isset($pathData['oldPath']) && !empty($pathData['oldPath'])) {
                $oldPathData = $this->readCacheEntry($pathData['oldPath']);
                if (isset($oldPathData['path']) && $oldPathData['path'] === $path) {
                    $oldPathData['path'] = $pathFinal;
                    $cacheKeyOld = $this->generateCacheKeyBy($pathData['oldPath']);
                    $this->cache->delete($cacheKeyOld);
                    $this->cache->get($cacheKeyOld, fn () => $oldPathData);
                }
            }
        }

        $cacheKey = $this->generateCacheKeyBy($path);
        $this->cache->delete($cacheKey);
    }

    /**
     * Read a cached entry and ensure the returned payload is an array.
     *
     * @param string $path
     * @return array|null
     */
    private function readCacheEntry(string $path): ?array
    {
        $payload = $this->findPath($path);
        return is_array($payload) ? $payload : null;
    }

    /**
     * Generate a stable cache key for a slug path.
     *
     * @param string $text
     * @return string
     */
    private function generateCacheKeyBy(string $text): string
    {
        return "wisymfony_slug_history.path.".md5($text);
    }
}
