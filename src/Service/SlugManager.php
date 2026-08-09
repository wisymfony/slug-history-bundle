<?php

declare(strict_types=1);

namespace Wisymfony\SlugHistoryBundle\Service;

use ReflectionClass;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Wisymfony\SlugHistoryBundle\Attribute\Slugged;

/**
 * Service that manages slug history mappings and generates redirect targets.
 *
 * Responsibilities:
 * - Detect changes on entity slug properties annotated with `Slugged`.
 * - Generate old and new route paths and store their mapping in cache.
 * - Provide a lookup to resolve an old path to the current path for 301 redirects.
 */
final class SlugManager
{
    private array $slugUpdateList = [];
    /**
     * Cache of parsed `Slugged` attribute metadata per class to avoid repeated reflection.
     *
     * @var array<string, array<int, array{name:string,attr:Slugged}>>
     */
    private array $sluggedCache = [];
    /**
     * Constructor.
     *
     * @param CacheInterface  $cacheInterface Cache used to store slug mappings.
     * @param RouterInterface $routerInterface Router used to generate route paths.
     */
    public function __construct(private CacheInterface $cacheInterface, private RouterInterface $routerInterface)
    {
    }

    public function getRouterInterface() : RouterInterface {
        return $this->routerInterface;
    }

    /**
     * Inspect the given object for `Slugged` attributes and prepare slug mappings.
     *
     * This method compares the previous and new slug values (from the provided
     * `$entityChangeSet` when available) and records any mapping from the old
     * route path to the new route path into an internal list for later persistence.
     *
     * @param object $object The entity instance to inspect (passed by reference).
     * @param array  $entityChangeSet Doctrine-style change set ([field => [old, new], ...]).
     *
     * @return void
     */
    public function applySlugged(object &$object, array $entityChangeSet = []): void
    {
        $sluggers = $this->getSlugged($object);
        foreach ($sluggers as $slugger) {
            if ($slugger['attr'] instanceof Slugged) {
                $attr = $slugger['attr'];
                
                $oldSlug = $this->getFieldValueString($object, $slugger['name']);
                if (
                    !empty($attr->from) &&
                    isset($entityChangeSet[$attr->from]) &&
                    !isset($entityChangeSet[$slugger['name']])
                ) {
                    $fromValue = $entityChangeSet[$attr->from][1];
                    $this->updateSlugField($object, $slugger['name'], $fromValue);
                }

                $newSlug = $this->getFieldValueString($object, $slugger['name']);
                if (isset($entityChangeSet[$slugger['name']])) {
                    $oldSlug = $entityChangeSet[$slugger['name']][0];
                    $newSlug = $entityChangeSet[$slugger['name']][1];
                }

                if (!empty($attr->routeName)) {
                    $oldRouteParams = [];
                    $newRouteParams = [];
                    if (!empty($attr->routeSlugParam)) {
                        $oldRouteParams[$attr->routeSlugParam] = $oldSlug;
                        $newRouteParams[$attr->routeSlugParam] = $newSlug;
                    }
                    if (!empty($attr->routeDefaultParams)) {
                        $oldRouteParams = array_merge($oldRouteParams, $attr->routeDefaultParams);
                        $newRouteParams = array_merge($newRouteParams, $attr->routeDefaultParams);
                    }

                    $oldPath = $this->routerInterface->generate($attr->routeName, $this->applyMapperRouteParams($object, $oldRouteParams));
                    $newPath = $this->routerInterface->generate($attr->routeName, $this->applyMapperRouteParams($object, $newRouteParams));
                    if ($oldPath != $newPath) {
                        $this->slugUpdateList[$oldPath] = [
                            'path' => $newPath,
                            'entityClass' => get_class($object),
                            'entityId' => method_exists($object, 'getId') ? $object->getId() : null,
                            'createdAt' => time(),
                            'oldPath' => $oldPath,
                        ];
                    }
                }
            }
        }
    }

    /**
     * Persist the collected slug mappings into cache.
     *
     * This method writes each recorded mapping to the configured `CacheInterface`.
     * It clears any existing entries for both the old and new path cache keys
     * before saving the mapping.
     *
     * @return void
     */
    public function saveSlugUpdateList(): void
    {
        if (!empty($this->slugUpdateList)) {
            foreach ($this->slugUpdateList as $oldPath => $newPathData) {
                $cacheKey = $this->generateCacheKeyBy($oldPath);
                $this->cacheInterface->delete($cacheKey);
                $this->cacheInterface->get($cacheKey, fn () => $newPathData);
            }
            $this->slugUpdateList = [];
        }
    }

    /**
     * Lookup the current (new) path for a previously known `oldPath`.
     *
     * @param string $oldPath The requested old path to resolve (e.g. `/blog/old-slug`).
     *
     * @return array|null The resolved current path if found in cache, or null when not found.
     */
    public function getNewPath(string $oldPath): ?array
    {
        $visited = [];
        $currentPath = $oldPath;
        $bestMatch = null;

        while ($currentPath !== null && !isset($visited[$currentPath])) {
            $visited[$currentPath] = true;
            $entry = $this->readCacheEntry($currentPath);

            if (!is_array($entry) || !isset($entry['path']) || !is_string($entry['path'])) {
                break;
            }

            $entryCreatedAt = isset($entry['createdAt']) ? (int) $entry['createdAt'] : 0;
            if ($bestMatch === null || $entryCreatedAt > $bestMatch['createdAt']) {
                $bestMatch = [
                    'path' => $entry['path'],
                    'createdAt' => $entryCreatedAt,
                    'entityClass' => $entry['entityClass'] ?? null,
                    'entityId' => $entry['entityId'] ?? null,
                ];
            }

            if ($entry['path'] === $currentPath) {
                break;
            }

            $currentPath = $entry['path'];
        }

        return $bestMatch;
    }

    /**
     * Generate the current route path(s) for an entity based on its `Slugged` attributes.
     *
     * Returns an array of absolute paths (strings). Useful for callers that need
     * to know the concrete path(s) an entity will occupy (for example to remove
     * any existing redirect entries when a new row is persisted).
     *
     * @param object $object The entity instance to inspect.
     *
     * @return string[] Array of absolute paths for the entity's slugged routes.
     */
    public function getPathsForEntity(object $object): array
    {
        $paths = [];
        $sluggers = $this->getSlugged($object);
        foreach ($sluggers as $slugger) {
            if ($slugger['attr'] instanceof Slugged) {
                $attr = $slugger['attr'];
                if (empty($attr->routeName)) {
                    continue;
                }

                $routeParams = [];
                $value = $this->getFieldValueString($object, $slugger['name']);
                if (!empty($attr->routeSlugParam) && $value !== null) {
                    $routeParams[$attr->routeSlugParam] = $value;
                }
                if (!empty($attr->routeDefaultParams)) {
                    $routeParams = array_merge($routeParams, $attr->routeDefaultParams);
                }

                $routeParams = $this->applyMapperRouteParams($object, $routeParams);
                $paths[] = $this->routerInterface->generate($attr->routeName, $routeParams);
            }
        }

        return $paths;
    }

    /**
     * Remove a saved slug path from cache and rewire its immediate predecessor.
     *
     * Behaviour:
     * - Reads the cache entry for `$path`. If the entry exists and contains a
     *   `'path'` value (the successor), and also contains an `'oldPath'` value,
     *   then the method will load the cache entry for that `oldPath` and,
     *   if it currently points to `$path`, update it to point to the successor.
     * - Finally the cache entry for `$path` itself is removed.
     *
     * Notes / limitations:
     * - This rewiring only updates a single predecessor stored in the
     *   `'oldPath'` field of the removed entry; it doesn't traverse or rebuild
     *   full predecessor lists or reverse-indexes.
     * - Cache keys are generated using `generateCacheKeyBy()`; callers that
     *   maintain additional reverse indices must keep them in sync separately.
     *
     * @param string $path Absolute route path to remove from slug history.
     */
    public function removePath(string $path) : void {
        $pathData = $this->readCacheEntry($path);
        if (is_array($pathData) && isset($pathData['path'])) {
            $pathFinal = $pathData['path'];
            if (isset($pathData['oldPath']) && !empty($pathData['oldPath'])) {
                $oldPathData = $this->readCacheEntry($pathData['oldPath']);
                if (isset($oldPathData['path']) && $oldPathData['path'] === $path) {
                    $oldPathData['path'] = $pathFinal;
                    $cacheKeyOld = $this->generateCacheKeyBy($pathData['oldPath']);
                    $this->cacheInterface->delete($cacheKeyOld);
                    $this->cacheInterface->get($cacheKeyOld, fn () => $oldPathData);
                }
            }
        }

        $cacheKey = $this->generateCacheKeyBy($path);
        $this->cacheInterface->delete($cacheKey);
    }

    private function applyMapperRouteParams(object $object, array $routeParams): array
    {
        $result = [];
        foreach ($routeParams as $key => $value) {
            $fieldValue = $value;
            if (is_string($value) && str_starts_with($value, '@')) {
                $fieldPath = explode('.', substr($value, 1));
                foreach ($fieldPath as $index => $fieldName) {
                    if ($index === 0) {
                        $fieldValue = $this->getFieldValue($object, $fieldName);
                    } else {
                        if (is_object($fieldValue)) {
                            $fieldValue = $this->getFieldValue($fieldValue, $fieldName);
                        } else {
                            $fieldValue = null;
                            break;
                        }
                    }
                }
            }
            $result[$key] = $fieldValue;
        }
        return $result;
    }

    private function readCacheEntry(string $path): ?array
    {
        $cacheKey = $this->generateCacheKeyBy($path);
        $payload = $this->cacheInterface->get($cacheKey, fn () => null);

        return is_array($payload) ? $payload : null;
    }

    /**
     * Get the value of a field from the object using a conventional getter.
     *
     * @param object $object    The entity instance.
     * @param string $fieldName The field/property name (without "get").
     *
     * @return string|null The value returned by the getter, or null if not available.
     */
    private function getFieldValueString(object $object, string $fieldName): ?string
    {
        $value = $this->getFieldValue($object, $fieldName);
        return is_string($value) ? $value : null;
    }

    private function getFieldValue(object $object, string $fieldName): mixed
    {
        $methodGetSlug = sprintf("get%s", ucfirst($fieldName));
        if (method_exists($object, $methodGetSlug)) {
            return $object->{$methodGetSlug}();
        }
        return null;
    }



    /**
     * Inspect the object with reflection and return properties annotated with `Slugged`.
     *
     * @param object $object The entity instance to scan.
     *
     * @return array<int, array{name:string,attr:Slugged}> List of found slug configurations.
     */
    private function getSlugged(object $object): array
    {
        $class = get_class($object);
        if (isset($this->sluggedCache[$class])) {
            return $this->sluggedCache[$class];
        }

        $slugged = [];
        $reflection = new ReflectionClass($object);
        foreach ($reflection->getProperties() as $reflectionProperty) {
            $sluggedAttr = $reflectionProperty->getAttributes(Slugged::class);
            if ($sluggedAttr && !empty($sluggedAttr)) {
                $slugged[] = [
                    "name" => $reflectionProperty->getName(),
                    "attr" => $sluggedAttr[0]->newInstance()
                ];
            }
        }

        $this->sluggedCache[$class] = $slugged;
        return $slugged;
    }

    /**
     * Update the slug field on the object by generating a slug from another value.
     *
     * @param object $object    Entity instance (by reference) to update.
     * @param string $slugField The slug property name to set.
     * @param string $fromValue Source string used to generate the slug.
     *
     * @return void
     */
    private function updateSlugField(object &$object, string $slugField, string $fromValue): void
    {
        if (!empty($fromValue)) {
            $methodSetSlugValue = sprintf("set%s", ucfirst($slugField));
            if (method_exists($object, $methodSetSlugValue)) {
                $slug = $this->generateSlgFrom($fromValue);
                $object->{$methodSetSlugValue}($slug);
            }
        }
    }

    /**
     * Generate a URL-friendly slug from arbitrary text.
     *
     * @param string $text    Source text to transliterate and clean.
     * @param string $divider Character used to separate words in the slug.
     *
     * @return string The generated slug (empty string for empty input).
     */
    private function generateSlgFrom(string $text, string $divider = '-'): string
    {
        if (trim($text) === '') {
            return '';
        }

        if (class_exists(\Transliterator::class)) {
            $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
            if (null !== $transliterator) {
                $text = $transliterator->transliterate($text);
            }
        } else {
            $text = (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            $text = mb_strtolower($text, 'UTF-8');
        }
        $text = (string) preg_replace('~[^\pL\d]+~u', $divider, $text);
        $text = (string) preg_replace('~[^-\w]+~', '', $text);
        $text = (string) preg_replace('~' . preg_quote($divider, '~') . '+~', $divider, $text);
        return trim($text, $divider);
    }

    /**
     * Generate a stable cache key for a given path.
     *
     * @param string $text The input text (usually a route path).
     *
     * @return string A string suitable as a cache key.
     */
    private function generateCacheKeyBy(string $text): string
    {
        return "wisymfony_slug_history.".md5($text);
    }
}
